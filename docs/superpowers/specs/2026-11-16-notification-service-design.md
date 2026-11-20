# Notification Service — Design

Date: 2026-11-16
Status: proposed
Source of truth: `diagrams/notification-service/notification-service-updated/` (the corrected diagrams)
Reference implementation: `erp/services/product-service` and, for consumers/outbox,
`erp/services/invoice-service`

## Intent

Build `notification-service` end to end inside `erp/services/notification-service`,
and its user-facing surface in `erp/frontend`, from the corrected notification
diagrams.

The service owns the ERP notification inbox and email delivery:

- Producers (order, inventory, payment, invoice, contact) publish a generic
  `NotificationRequested` event.
- The service deduplicates events, resolves an audience, applies recipient
  preferences, renders a template, and stores one inbox item per recipient.
- Email deliveries are stored as `QUEUED` rows in the same transaction as the
  notification (the outbox). A worker reads them from the database, sends them,
  retries temporary failures, and lets an administrator requeue permanent ones.
- Users read, mark read, archive, and configure their notifications from the ERP
  frontend.

The inbox item lifecycle (`UNREAD → READ → ARCHIVED`) and the email delivery
lifecycle (`QUEUED → SENDING → SENT`, with `RETRY_WAITING` and `FAILED`) stay
separate, as the corrected diagrams require.

## Goals

- Implement the corrected class model: `Notification`, `Delivery`,
  `DeliveryAttempt`, `NotificationPreference`, `NotificationTemplate`,
  `ProcessedEvent`, `Setting`, and the `NotificationStatus`, `DeliveryStatus`,
  `Channel`, and `Severity` enums.
- Enforce both state machines in the entities, not in controllers.
- Consume `NotificationRequested` idempotently: the event id is reserved by a
  unique key before any notification is written; a duplicate is acknowledged
  without side effects.
- Send invalid messages to a dead-letter path, not just a log line.
- Store `QUEUED` email deliveries in the same transaction as the notification;
  no email can be lost between commit and publish.
- Deliver email through Symfony Mailer with retry/backoff, attempt history, and
  administrator requeue.
- Expose a versioned REST API under `/api/v1` with the same envelope,
  validation, pagination, error format, and Nelmio documentation as
  `product-service`.
- Authenticate callers and resolve role audiences through the
  `authentication-service` HTTP boundary (decision below).
- Ship the frontend: header bell with unread count, notification inbox,
  preferences, and an administrator view for deliveries, templates, and
  settings.
- Ship the same quality gates as the neighbouring services: PHPUnit, PHPStan,
  Rector, PHP-CS-Fixer, Docker images, compose file, and gateway route.

## Non-goals

- Real-time push (WebSocket/SSE), mobile push, SMS, or chat channels.
- A full-blown notification campaign/segment builder.
- Changes to producer services. They will publish `NotificationRequested` later;
  this design defines the contract and provides a local command to exercise it.
- Changes to the invoice, order, payment, or contact APIs.
- Per-recipient locale in this iteration. Auth users carry no locale, so every
  template is rendered with `notifications.default_locale`; the template table
  stays locale-keyed so a future auth change can use it without a migration.
- Exactly-once email. The worker guarantees at-least-once: a crash after the
  provider accepts but before the delivery is marked sent can resend one email.

## Anti-goals

- One status column mixing inbox state and email delivery state.
- Sending email synchronously while handling the inbound event.
- Publishing a delivery job to RabbitMQ after the DB commit (the lost-email
  window the corrected architecture removes).
- Check-then-insert deduplication that races between parallel consumers.
- Trusting identity headers the gateway does not validate.
- Editing an already archived notification back into the inbox.

## Constraints

- PHP >= 8.4, Symfony 8.1, Doctrine ORM 3, MySQL 8.4, PHPUnit 13.
- The database is the source of truth; Redis is an optional cache.
- UUIDs (`symfony/uid`, Doctrine `uuid` type, v7) for notifications,
  deliveries, attempts, and processed-event ids.
- Auth users have integer ids. `recipientId`, `userId` in preferences, and
  audience ids are integers, deliberately diverging from the diagram's `Uuid`
  notation because the auth service owns that identity.
- Tests use SQLite in memory, rebuild the schema per test, and never call the
  real auth service, broker, SMTP server, or filesystem outside `var/`.
- The service owns its database. No cross-service joins.

## Approaches considered

1. **Layered product-service mirror with a DB-outbox worker (chosen).**
   `Api/V1/{Controller,Request,Mapper,Resource,Documentation}`, `Entity`, `Enum`,
   `Message`, `MessageHandler`, `Handler`, `Repository`, `Mail`, `Security`,
   `Console`, `Utils`, plus `Build/` tool configs. Handlers own the transaction
   boundary; the entity owns the state machines. This keeps the service
   reviewable side by side with `product-service` and matches the corrected
   architecture exactly.
2. **Messenger delivery jobs instead of a DB outbox.** Store the notification,
   then dispatch a `SendDelivery` message to RabbitMQ. Simpler worker code, but
   it recreates the lost-email window between commit and publish that the
   corrected diagram explicitly rejects. Rejected.
3. **Single notification table with a combined lifecycle and synchronous
   email.** Fastest to write, but "sent" would wrongly imply "read", archiving
   would be tangled with delivery, retries would block the consumer, and the
   administrator requeue has no target. Rejected.

## Decisions

### Service layout

```
services/notification-service/
  src/Api/V1/{Controller,Documentation,Mapper,Request,Resource}
  src/Entity, src/Enum
  src/Message/NotificationRequested.php
  src/MessageHandler/NotificationRequestedHandler.php
  src/Handler/Notification/{CreateNotificationsHandler,PurgeNotificationsHandler}.php
  src/Handler/Delivery/{ProcessDeliveriesHandler,RequeueDeliveryHandler}.php
  src/Repository
  src/Mail/{DeliverySender,EmailDeliverySender}
  src/Security/{Caller,AuthServiceClient,CallerResolver}
  src/Console/{SendNotificationCommand,ProcessDeliveriesCommand,PurgeNotificationsCommand}
  src/EventListener/ApiExceptionListener.php
  src/Exceptions
  src/DataFixtures/V1
  src/Utils/{ApiResponse,Pagination}
  src/Traits/ShareableTrait.php (copied lifecycle trait)
  tests/{Unit,Controller,Integration}
  Build/{cs-fixer,rector,stan,unit}
  docker/, Dockerfile, nginx.Dockerfile, phpunit.xml, .env, .php-cs-fixer.dist.php
```

The default response envelope, pagination helper, `ApiException` hierarchy,
`ApiExceptionListener`, `ShareableTrait`, and `Build/` configurations are copied
from `product-service` so all services behave the same way.

### Domain model

`Notification` (aggregate root for the inbox item):

| Field | Type | Notes |
|---|---|---|
| `id` | `Uuid` | v7, generated in constructor |
| `recipientId` | `int` | auth-service user id |
| `type` | `string(120)` | event type, e.g. `payment.installment.overdue` |
| `title` | `string(255)` | rendered from the template subject |
| `body` | `text` | rendered from the template body |
| `link` | `?string(500)` | optional frontend deep link |
| `severity` | `Severity` | `info`, `success`, `warning`, `critical` |
| `status` | `NotificationStatus` | `unread`, `read`, `archived` |
| `visibleInApp` | `bool` | false when the recipient disabled the `in_app` channel; the row is still the parent of an email delivery |
| `dedupKey` | `?string(190)` | business dedup key from the event |
| `readAt` / `archivedAt` | `?DateTimeImmutable` | set by transitions |
| `createdAt` | `DateTimeImmutable` | constructor |

Methods: `markAsRead()` (only `unread`; `archived` throws
`InvalidNotificationTransition`), `archive()` (from `unread` or `read`; already
archived is a no-op), `isUnread()`.

The inbox list, unread count, read-all and archive-read queries always filter
`visible_in_app = true`. A notification created only for email is therefore
never shown in the bell or inbox, while still parent-ing its delivery. This keeps
the channel preference honest without a read-time join against preferences.

Unique index on `(recipient_id, dedup_key)` where `dedup_key` is not null. This
protects producers that reuse a business key with a fresh event id; the event id
protects transport retries.

`Delivery`:

| Field | Type | Notes |
|---|---|---|
| `id` | `Uuid` | v7 |
| `notification` | `ManyToOne` | cascade delete |
| `channel` | `Channel` | only `email` is created today; enum keeps `in_app` for the model |
| `status` | `DeliveryStatus` | `queued`, `sending`, `sent`, `retry_waiting`, `failed` |
| `attempts` | `int` | incremented by the worker |
| `recipientEmail` | `string(180)` | snapshot taken at enqueue time |
| `subject` | `string(255)` | rendered email subject snapshot |
| `body` | `text` | rendered email HTML snapshot |
| `nextAttemptAt` | `?DateTimeImmutable` | null until queued |
| `sendingAt` | `?DateTimeImmutable` | set when claimed, used to reclaim crashed workers |
| `lastError` | `?text` | last human-readable failure |
| `sentAt` | `?DateTimeImmutable` | provider accepted |
| `createdAt` | `DateTimeImmutable` | |

Methods: `markSending()`, `markSent()`, `scheduleRetry(backoffSeconds, error)`,
`markFailed(error)`, `reclaimStale()`, `requeue()`. `markSent()` records nothing
itself; the handler creates the `DeliveryAttempt`. `reclaimStale()` accepts only a
`SENDING` row older than the configured timeout and moves it to `RETRY_WAITING`
with `nextAttemptAt = now`, incrementing attempts and recording
`Reclaimed after timeout`; the worker then retries it like any other temporary
failure. `requeue()` only accepts `failed`, resets attempts to 0, clears
`lastError`, sets `queued` and `nextAttemptAt = now`.

The recipient address and the rendered email content are snapshotted into the
delivery row. The worker never calls the auth service or re-renders templates, so
template edits and user renames after enqueue cannot change queued mail, and
admin search can filter by `recipientEmail`.

`DeliveryAttempt`: uuid, delivery relation, `attemptNumber`, `providerMessageId`,
`error`, `attemptedAt`. One row per send attempt.

`NotificationPreference`: `userId` (int), `type` (string), `channel` (`Channel`),
`enabled` (bool). Unique `(user_id, type, channel)`. Missing row means enabled.

`NotificationTemplate`: `id` (UUID, generated), `type`, `channel`, `locale`,
`subject`, `bodyTemplate`. Unique `(type, channel, locale)`. Subject doubles as
the inbox title; the body template produces the inbox body and email HTML. The
surrogate id makes the admin REST resource addressable as
`/admin/templates/{id}`; the natural key remains the upsert key.

`ProcessedEvent`: `eventId` (uuid primary key), `source`, `processedAt`. Inserted
first inside the transaction; the primary key is the concurrency guard.

`Setting`: `key` (primary key), `value`, `description`. Seeded with
`notifications.retention_days`, `deliveries.max_attempts`,
`deliveries.retry_base_seconds`, `deliveries.retry_max_seconds`,
`deliveries.sending_timeout_seconds` (default 600),
`mailer.from_address`, `mailer.from_name`, `notifications.default_locale`.

There is no `Recipient` table. The user chose the auth-service HTTP boundary, so
the read model is a short-lived cache inside `AuthServiceClient` instead of a
replicated table. This is a deliberate deviation from the corrected class
diagram, recorded here so it is not mistaken for an omission.

### State machines

`NotificationStatus`:

- `unread → read` via `markAsRead()`
- `unread|read → archived` via `archive()`
- `archived` is terminal for the inbox; the retention command deletes archived
  rows after the configured period.
- `read → unread` is never allowed.

`DeliveryStatus`:

- `queued → sending` when the worker claims it
- `sending → sent` when the provider accepts
- `sending → retry_waiting` on a temporary failure while attempts < max
- `sending → retry_waiting` through `reclaimStale()` when a claimed row is older
  than `deliveries.sending_timeout_seconds` (crashed worker), without waiting
  for an administrator
- `sending → failed` on a permanent failure or when attempts >= max
- `retry_waiting → queued` when backoff elapses (the worker treats due rows as claimable)
- `failed → queued` only through the administrator requeue action
- `sent` is terminal. It means the provider accepted the message, not that the
  user read it.

Enums are string-backed with lowercase values (`unread`, `retry_waiting`, ...);
the API and frontend use those values. Labels live in the frontend.

### Event contract

`NotificationRequested` is a JSON message on a dedicated fanout exchange
`notification_events`:

```json
{
  "eventId": "6f1c2d1e-7a0b-4c55-9f6e-2b8d3a9c1e10",
  "occurredAt": "2026-11-15T09:00:00Z",
  "source": "payment-service",
  "type": "payment.installment.overdue",
  "audience": { "role": "ACCOUNTANT" },
  "params": { "invoiceNumber": "F-012", "amount": 20000, "currency": "TND" },
  "link": "/dashboard/invoices/9d2b7c1a-0000-0000-0000-000000000000",
  "severity": "warning",
  "dedupKey": "installment-3f2a:2026-11-15"
}
```

Rules:

- `eventId`, `occurredAt`, `source`, `type`, `audience`, `params` are required.
- `audience` must contain exactly one of `role` (string) or `userIds` (non-empty
  array of positive integers). `role` is normalized by prefixing `ROLE_` when
  absent. `userIds` is additive support for direct notifications.
- `severity` defaults to `info` and must be one of the enum values.
- `link` must start with `/` when present.
- `params` is a flat string/number/boolean map used by the renderer.
- Only additive changes are allowed to the contract. `dedupKey` is optional.
- Unknown or malformed payloads throw `UnrecoverableMessageHandlingException`,
  which sends them to the failed/dead-letter transport.

`NotificationRequested` is a readonly DTO with a `fromArray()` factory that
performs this validation and raises `InvalidNotificationEvent` with a precise
reason. The handler catches nothing.

### Consuming and idempotency

`NotificationRequestedHandler`:

1. Resolve the audience through `AuthServiceClient` (HTTP, read-only). This
   happens before opening the transaction so no HTTP call holds a DB
   transaction.
2. Open one transaction:
   - `persist(ProcessedEvent)` and `flush()`. A unique-key violation means the
     event id was already processed: roll back and return (ack).
   - For each recipient, check `NotificationPreference` for `in_app` and
     `email`; skip recipients with both channels disabled and log a suppression
     line.
   - Render the template for each enabled channel at the default locale. Use the
     `(type, channel, locale)` row, fall back to the default locale, then to a
     built-in generic template so a missing template never drops a message.
   - Persist one `Notification` per recipient with
     `visibleInApp = in_app preference`, plus a `Delivery` with status `queued`
     when email is enabled, carrying the recipient email and the rendered email
     subject/body. The notification row exists even for email-only recipients so
     the delivery has a parent; it is hidden from every inbox query.
   - Commit.
3. Acknowledge the message. Any database failure rolls the whole transaction
   back; Messenger retries according to the transport retry strategy.

The `(recipient_id, dedup_key)` unique index is checked by the insert; a conflict
skips that recipient without failing the event.

The tests cover: new event, duplicate event id, duplicate dedup key, invalid
payload, role audience, explicit user ids, disabled channels, email-only
notifications staying out of the inbox, the delivery content snapshot, missing
template, and the single-transaction guarantee (notification + queued delivery).

### Authentication and audience resolution

This is the one addition outside the notification service, agreed with the
requester.

New internal endpoint in `authentication-service`:

```
GET /api/authenticator/internal/users?role=ACCOUNTANT
GET /api/authenticator/internal/users?ids=1,2
X-INTERNAL-TOKEN: <shared secret>
```

Response: `{"data":[{"id":1,"email":"...","fullName":"...","roles":["ROLE_USER","ROLE_ACCOUNTANT"]}]}`.
Only verified users are returned. The controller compares the shared secret with
`hash_equals` against `AUTH_INTERNAL_TOKEN`; the path is `PUBLIC_ACCESS` in the
security firewall because the token, not a user session, authorizes it. The
gateway returns 404 for `/api/authenticator/internal/` so the endpoint is only
reachable on the Docker network.

Notification-service side:

- `CallerResolver` reads `X-AUTH-TOKEN`, calls
  `GET {AUTH_SERVICE_URL}/api/authenticator/me`, and returns a `Caller`
  (int id, email, fullName, roles). The result is cached by token hash for 60
  seconds in `cache.app` (Redis in compose, filesystem in tests).
- Missing/invalid token → `UNAUTHENTICATED` (401).
- `AuthServiceClient::resolveAudience()` calls the internal endpoint for a role
  or id list and returns `Recipient` value objects. HTTP failures → 503
  `AUTH_SERVICE_UNAVAILABLE`, so Messenger retries the event.
- Admin endpoints require `ROLE_ADMIN`; anything else → `FORBIDDEN` (403).

A direct `userIds` audience is also validated against the auth service; ids that
do not resolve are skipped and logged.

`authentication-service` fixtures are updated so the demo path works end to end:
the existing demo/test users become verified with a `fullName`, a
`ROLE_ACCOUNTANT` user is added, and a `ROLE_ADMIN` user is added. Credentials
are documented in the service `.env`/README so the admin UI can be exercised.
This is a development-fixture change, not a schema or production API change.
Auth users still carry no locale; the notification service renders at the
default locale as stated in the non-goals.

### REST API

Prefix `/api/v1`, gateway path `/api/notification/`, so the frontend calls
`/api/notification/...`.

User endpoints:

| Method | Path | Purpose |
|---|---|---|
| GET | `/notifications` | paginated inbox for the caller; filters `status`, `type`, `severity`, `search`; returns `meta.unreadCount` |
| GET | `/notifications/unread-count` | `{count}` for the header bell |
| GET | `/notifications/{id}` | one notification, owner only |
| POST | `/notifications/{id}/read` | idempotent mark read |
| POST | `/notifications/read-all` | mark every unread notification read |
| POST | `/notifications/{id}/archive` | archive |
| POST | `/notifications/archive-read` | archive every read notification |
| GET | `/preferences` | matrix rows for every template type × channel with effective enabled state |
| PUT | `/preferences` | upsert a list of `{type, channel, enabled}` for the caller |

Admin endpoints (`ROLE_ADMIN`):

| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/deliveries` | paginated deliveries; filters `status`, `channel`, `type`, `search` by recipient/event type |
| GET | `/admin/deliveries/{id}` | delivery with its attempts |
| POST | `/admin/deliveries/{id}/requeue` | `failed → queued` |
| GET/POST | `/admin/templates` | list / create a template |
| GET/PUT/DELETE | `/admin/templates/{id}` | read / update / delete by UUID |
| GET | `/admin/settings` | all settings |
| PUT | `/admin/settings/{key}` | update one value |

Response envelope: `{"status":"success","data":...}`, plus `pagination` for lists
and an optional `meta` object for list-level facts. The copied `ApiResponse` gains
an optional `meta` argument, so the shape stays compatible with the other
services while the inbox list can return `meta.unreadCount` without abusing
`pagination`.
Errors: `{"status":"error","error":{"code":"...","message":"..."}}` through the
copied `ApiExceptionListener`. Validation errors from `#[MapRequestPayload]`
keep Symfony's 422 format, as in the other services.

DTOs use `#[MapQueryString]` / `#[MapRequestPayload]`, readonly constructor
promotion, and `#[Assert\...]` constraints. Pagination uses the copied
`Pagination` helper and the same `currentPage/totalPages/pageSize/totalItems/links`
shape.

### Email delivery worker

`app:deliveries:process --limit=50`:

1. Reclaim stale `sending` deliveries whose `sendingAt` is older than
   `deliveries.sending_timeout_seconds`, through `reclaimStale()`, so a crashed
   worker cannot strand a row forever.
2. Claim due deliveries (`queued` or `retry_waiting` with `nextAttemptAt <= now`)
   with an atomic conditional update that sets `sending`, `sendingAt` and
   increments attempts only when the row is still due and claimable. This is
   portable to SQLite and safe with multiple workers; on MySQL the candidate
   selection may additionally use `SKIP LOCKED`.
4. Send through `EmailDeliverySender` using the delivery's snapshotted recipient,
   subject and HTML body, and the from address/name from settings.
5. Success: `markSent()`, store the provider message id in a `DeliveryAttempt`,
   commit.
6. Temporary failure: if attempts < max, `scheduleRetry(base * 2^(attempts-1))`
   capped at the maximum; otherwise `markFailed()`. Permanent failures (invalid
   address / `RfcComplianceException`) fail immediately. Every path writes a
   `DeliveryAttempt` with the error.
7. Exceptions never kill the loop; each delivery is committed independently.

Email delivery is at-least-once by design. The stale-claim recovery covers
crashes before or during the send; a crash after the provider accepted but
before the delivery was marked sent can produce one duplicate. That is accepted
over silently losing the email.

`app:notifications:purge` deletes archived notifications older than
`notifications.retention_days` (cascading deliveries and attempts).

`app:notifications:send --type=... --role=... --param=key=value` builds a
`NotificationRequested` and calls the handler synchronously. It exists so the
service and frontend can be exercised before producer services publish the
contract; it is not part of the API.

The compose file runs:

- `notification-service` (php-fpm),
- `notification-service-web` (nginx),
- `notification-service-consumer` (`messenger:consume notification_events`),
- `notification-service-worker` (a `while` loop around `app:deliveries:process`),
- `notification-db` (MySQL 8.4), optional `notification-cache-redis`,
- optional `mailpit` for local email inspection (`MAILER_DSN=smtp://mailpit:1025`).

The image, nginx config, Consul registration, health checks, and PHP config are
copied from `product-service`. `CONSUL_SERVICE_NAME=notification-service`.

### Frontend

New files, following the existing invoice/contact patterns:

- `src/services/notifications-api.tsx`: types, envelope unwrapping, all API
  calls, `formatRelativeTime` helper, unread-count helper.
- `src/components/notifications/notification-bell.tsx`: bell in `SiteHeader`
  with unread badge, popover listing the five latest unread items, "View all",
  mark-read action, polling every 60 seconds and on route change.
- `src/pages/dashboard/notifications/page.tsx`: inbox with All/Unread/Archived
  tabs, severity filter, debounced search, mark read, archive, mark all read,
  pagination, empty/loading/error states. Clicking an item marks it read and
  follows its `link` when present.
- `src/pages/dashboard/notifications/preferences.tsx`: type × channel matrix
  with switches and one Save action, optimistic state disabled until saved.
- `src/pages/dashboard/notifications/admin.tsx`: tabs for deliveries (filter,
  inspect attempts, requeue failed), templates (edit subject/body per
  type/channel/locale), and settings (edit values by key).
- Routes in `src/main.tsx`; sidebar entry `Notifications`; site-header
  breadcrumb label; user-menu "Notifications" link points to the inbox.
- Vitest tests for the API module and unread-count/relative-time helpers,
  matching `products-api.test.ts` / `invoices-api` style.

The existing axios instance already sends `X-AUTH-TOKEN`, so the service needs no
new client configuration.

### Persistence and migrations

MySQL 8.4 tables: `notifications`, `deliveries`, `delivery_attempts`,
`notification_preferences`, `notification_templates`, `processed_events`,
`settings`. Indices:

- `notifications (recipient_id, visible_in_app, status, created_at)` for the inbox
- `notifications (recipient_id, dedup_key)` unique, nullable
- `deliveries (status, next_attempt_at)` for the worker queue
- `deliveries (status, sending_at)` for stale-claim recovery
- `deliveries (recipient_email)` for admin search
- `notification_preferences (user_id, type, channel)` unique
- `notification_templates (type, channel, locale)` unique
- `notification_templates` primary key on its UUID `id`
- `processed_events` primary key on `event_id`

One migration, written with the DBAL schema API so it runs on MySQL in compose
and SQLite in tests.

Seed fixtures: templates for `payment.installment.overdue`,
`invoice.issued`, `order.confirmed`, and a generic fallback; the settings
defaults; a preference override example; and a handful of notifications for
user 1 so the inbox is not empty in development.

### Tests and quality gates

- Unit: notification transitions, delivery transitions/backoff, template
  rendering and fallbacks, event validation, audience normalization, settings
  cache, caller resolution.
- Controller (WebTestCase, SQLite, mocked `AuthServiceClient`): inbox filters,
  ownership 404, read/archive idempotency, read-all, preferences, admin
  authorization 403, deliveries list/requeue, template CRUD, settings update.
- Integration: handler idempotency (event id, dedup key), one-transaction
  notification+delivery, role/user-id audiences, disabled channels, email-only
  notifications hidden from the inbox, missing template, invalid payload;
  worker success/retry/permanent failure/requeue/stale-send reclaim with a fake
  mailer transport.
- authentication-service: internal users endpoint rejects a missing/wrong token,
  filters by role and ids, excludes unverified users; fixtures provide verified
  admin and accountant users.
- Frontend: `pnpm test`, `pnpm lint`, `pnpm build`.
- Backend: `composer stan:check`, `rector:check`, `cs:check`, `unit:test`,
  `bin/console lint:container`, `lint:yaml config/`.

## What is left to the builder

- Exact wording of seeded template subjects/bodies and the generic fallback.
- Table/column naming details beyond the indices above (follow Doctrine's
  underscore strategy).
- The precise visual treatment of the bell popover and the admin tabs, within
  the existing shadcn/Tailwind system.
- Whether the admin pages are linked in the sidebar or only reachable from the
  inbox header. The design assumes the latter, with a visible "Admin" button in
  the notifications page header.
- Retry constants and polling intervals beyond the stated defaults.
