# Notification Service Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the notification service backend and frontend from the corrected notification diagrams, following `product-service` conventions.

**Architecture:** Layered Symfony 8.1 service (`Api/V1`, `Entity`, `Handler`, `Message`, `MessageHandler`, `Repository`, `Mail`, `Security`, `Console`) with two state machines, an idempotent `NotificationRequested` consumer, a DB outbox of queued email deliveries processed by a worker, and a React inbox/bell/preferences/admin UI.

**Tech Stack:** PHP 8.4, Symfony 8.1, Doctrine ORM 3, MySQL 8.4, Symfony Messenger + AMQP, Symfony Mailer, Symfony HttpClient, Symfony UID, NelmioApiDocBundle, PHPUnit 13, PHPStan, Rector, PHP-CS-Fixer, React 19, Vite, Vitest, shadcn/Tailwind.

**Spec:** `docs/superpowers/specs/2026-11-16-notification-service-design.md`

## Global Constraints

- PHP >= 8.4, Symfony 8.1.*, Doctrine ORM ^3.7, MySQL 8.4, PHPUnit ^13.4, PHPStan level 5.
- Response envelope `{"status":"success","data":...}`, optional `pagination` and `meta`; errors `{"status":"error","error":{"code","message"}}`.
- Notification, delivery, attempt, template and processed-event ids are UUID v7 (`symfony/uid`, Doctrine `uuid`).
- Auth user ids, preference user ids and audience ids are integers.
- API base `/api/v1`; each controller route owns its resource path; gateway path is `/api/notification/`.
- Tests run on SQLite and never call the real auth service, RabbitMQ, SMTP, or the network.
- Email delivery is at-least-once; archived notifications are purged by retention.
- Enums are string-backed with lowercase values (`unread`, `retry_waiting`, `in_app`, ...).

## Review Focus

- Duplicate `eventId` with a different payload: no new rows, message acked.
- Auth service unavailable during a role audience: transaction never partially commits; Messenger retries.
- In-app disabled, email enabled: no inbox item or unread count, but one queued email.
- Worker dies after claiming a delivery: the row is reclaimed after the timeout instead of staying `sending` forever.
- Missing template or missing placeholder value: message still renders/sends; one recipient never aborts the whole event.

---

### Task 1: Service Skeleton, Tooling, and Deployment

**Files:**
- Modify: `services/notification-service/composer.json`, `services/notification-service/.env`, `.gitignore`, `.semgrepignore`
- Create: `services/notification-service/config/packages/{messenger,nelmio_api_doc,property_info,validator,twig,http_client,cache}.yaml`, `config/routes/{api_v1,nelmio_api_doc,framework}.yaml`, `config/services_test.yaml`, `config/packages/test/*` as needed
- Create: `services/notification-service/{phpunit.xml,.php-cs-fixer.dist.php,trivy.yaml}`, `Build/{cs-fixer,rector,stan,unit}/...`, `docker/php.ini`, `docker/php-fpm.conf`, `docker/nginx.conf`, `Dockerfile`, `nginx.Dockerfile`, `tests/bootstrap.php`, `src/Kernel.php`, `public/index.php`, `bin/console`
- Create: `docker-compose.notification.yml`; modify `docker-compose.yml` (include + volumes), `infrastructure/gateway/nginx.conf`, root `.env`
- Test: `services/notification-service/tests/Integration/ServiceBootTest.php`

**Interfaces:**
- Produces: a bootable `App\Kernel`, `doctrine.orm.default_entity_manager`, Messenger transports `notification_events`/`failed`, gateway route `/api/notification/` → `notification-service-web:8080` rewriting to `/api/v1/`, compose services `notification-service`, `notification-service-web`, `notification-service-consumer`, `notification-service-worker`, `notification-db`, `notification-cache-redis`, `mailpit`.

- [ ] **Step 1: Write the failing boot test**

```php
final class ServiceBootTest extends KernelTestCase
{
    public function testKernelBootsWithDoctrine(): void
    {
        self::bootKernel();
        self::assertNotNull(self::getContainer()->get(EntityManagerInterface::class));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd services/notification-service && php bin/phpunit tests/Integration/ServiceBootTest.php`
Expected: FAIL — the current skeleton has no test bootstrap/EntityManager in the test environment.

- [ ] **Step 3: Install and configure the stack**

Run:
```bash
cd services/notification-service
composer require doctrine/doctrine-bundle doctrine/doctrine-migrations-bundle doctrine/orm \
  nelmio/api-doc-bundle phpdocumentor/reflection-docblock phpstan/phpdoc-parser predis/predis \
  symfony/amqp-messenger symfony/asset symfony/cache symfony/console symfony/dotenv symfony/http-client \
  symfony/mailer symfony/messenger symfony/mime symfony/property-access symfony/property-info \
  symfony/runtime symfony/serializer symfony/twig-bundle symfony/uid symfony/validator symfony/yaml
composer require --dev doctrine/doctrine-fixtures-bundle friendsofphp/php-cs-fixer phpstan/phpstan \
  phpstan/phpstan-doctrine phpunit/phpunit rector/rector symfony/browser-kit symfony/css-selector \
  symfony/maker-bundle
```
Copy the `Build/`, `phpunit.xml`, `tests/bootstrap.php`, `Dockerfile`, `nginx.Dockerfile`, `docker/`, and tool configs from `services/product-service`, renaming product-specific paths and image names. Configure:
- `.env`: `APP_ENV=dev`, `DATABASE_URL` notification DB, `MESSENGER_TRANSPORT_DSN`, `MAILER_DSN=null://null`, `REDIS_URL`, `AUTH_SERVICE_URL=http://authentication-service-web`, `AUTH_INTERNAL_TOKEN=change_internal_token`.
- `.env.test`: `DATABASE_URL='sqlite:///%kernel.project_dir%/var/data_test.db'`, `MAILER_DSN='null://null'`, `MESSENGER_TRANSPORT_DSN='in-memory://'`.
- `config/packages/messenger.yaml`: `failure_transport: failed`; transport `notification_events` on `%env(MESSENGER_TRANSPORT_DSN)%` with `exchange: {name: notification_events, type: fanout}` and a 3-attempt retry; `failed: doctrine://default?queue_name=failed`; tests use `in-memory://` for both.
- `config/routes.yaml`: import `../src/Api/V1/Controller/` under `/api/v1` (create the directory with `.gitkeep` in this task).
- `config/services.yaml`: autowire/autoconfigure `App\`, bind `%env(AUTH_SERVICE_URL)%` and `%env(AUTH_INTERNAL_TOKEN)%` to named constructor arguments where needed.

- [ ] **Step 4: Wire Docker and the gateway**

Create `docker-compose.notification.yml` with php-fpm, nginx web (`8080` exposed internally, matching product), consumer (`messenger:consume notification_events --time-limit=3600 --memory-limit=128M`), worker (`while true; do php bin/console app:deliveries:process --limit=50; sleep 10; done`), MySQL 8.4 (`notification_db_data`), Redis 7, and Mailpit (`axllent/mailpit`, ports `8025`); set the service `MAILER_DSN=smtp://mailpit:1025` in compose. Share `AUTH_INTERNAL_TOKEN` between the auth and notification services from the root `.env`. Give the service the same Consul env as product (`CONSUL_SERVICE_NAME=notification-service`, port `9000`), the same watch directives, and `develop` watches for PHP/nginx files. Add `notification-service` and `notification-service-web` to the gateway:
```nginx
location /api/notification/ {
    set $upstream notification-service-web:8080;
    rewrite ^/api/notification/(.*)$ /api/v1/$1 break;
    proxy_pass http://$upstream;
}
```
Add the include and `notification_db_data` / `notification_redis_data` volumes to `docker-compose.yml`; add DB/internal-token/mailer variables to root `.env`.

- [ ] **Step 5: Run the boot test and container lints**

Run:
```bash
cd services/notification-service
php bin/phpunit tests/Integration/ServiceBootTest.php
php bin/console lint:container
php bin/console lint:yaml config/
cd ../.. && docker compose config >/dev/null
```
Expected: PASS, all commands exit 0.

- [ ] **Step 6: Commit**

```bash
git add services/notification-service docker-compose.notification.yml docker-compose.yml infrastructure/gateway/nginx.conf .env
git commit -m "chore(notification): scaffold service, tooling and deployment"
```

---

### Task 2: Domain Enums and Entities with State Machines

**Files:**
- Create: `src/Enum/{NotificationStatus,DeliveryStatus,Channel,Severity}.php`
- Create: `src/Entity/{Notification,Delivery,DeliveryAttempt,NotificationPreference,NotificationTemplate,ProcessedEvent,Setting}.php`, `src/Traits/ShareableTrait.php`
- Create: `src/Exceptions/{ApiException,InvalidNotificationTransition}.php`
- Test: `tests/Unit/Entity/NotificationTest.php`, `tests/Unit/Entity/DeliveryTest.php`

**Interfaces:**
- `Notification::__construct(int $recipientId, string $type, string $title, string $body, Severity $severity, bool $visibleInApp, ?string $link = null, ?string $dedupKey = null)`
- `Notification::markAsRead(): void`, `archive(): void`, `isUnread(): bool`, `getId(): Uuid`, plus getters.
- `Delivery::__construct(Notification $notification, Channel $channel, string $recipientEmail, string $subject, string $body)`
- `Delivery::markSending(): void`, `markSent(): void`, `scheduleRetry(int $backoffSeconds, string $error): void`, `markFailed(string $error): void`, `reclaimStale(): void`, `requeue(): void`, getters.
- `DeliveryAttempt::__construct(Delivery $delivery, int $attemptNumber, ?string $providerMessageId, ?string $error)`
- `NotificationPreference::__construct(int $userId, string $type, Channel $channel, bool $enabled)`, `setEnabled(bool): void`
- `NotificationTemplate::__construct(string $type, Channel $channel, string $locale, string $subject, string $bodyTemplate)`, `update(string $subject, string $bodyTemplate): void`, `getId(): Uuid`
- `ProcessedEvent::__construct(string $eventId, string $source)` with UUID primary key `eventId`.
- `Setting::__construct(string $key, string $value, string $description)`, `setValue(string): void`

- [ ] **Step 1: Write failing state-machine tests**

```php
public function testUnreadCanBeReadThenArchived(): void
public function testArchivedCannotBeReadAgain(): void // expects InvalidNotificationTransition
public function testQueuedDeliveryBecomesSendingThenSent(): void
public function testTemporaryFailureSchedulesRetryWithError(): void
public function testRetryAfterMaxIsFailedAndRequeueResets(): void
public function testStaleSendingIsReclaimedToRetryWaiting(): void
```
Assert the exact statuses and timestamps (`readAt`/`archivedAt` not null; `attempts` increments on claim/reclaim; `requeue()` only accepts `failed`).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php bin/phpunit tests/Unit/Entity`
Expected: FAIL — classes do not exist.

- [ ] **Step 3: Implement enums and entities**

Use string-backed enums:
```php
enum NotificationStatus: string { case UNREAD='unread'; case READ='read'; case ARCHIVED='archived'; }
enum DeliveryStatus: string { case QUEUED='queued'; case SENDING='sending'; case SENT='sent'; case RETRY_WAITING='retry_waiting'; case FAILED='failed'; }
enum Channel: string { case IN_APP='in_app'; case EMAIL='email'; }
enum Severity: string { case INFO='info'; case SUCCESS='success'; case WARNING='warning'; case CRITICAL='critical'; public static function default(): self; public static function fromString(?string $value): self; }
```
Entities use Doctrine attributes, UUID v7 in constructors, `ShareableTrait` (`createdAt`, `updatedAt`, lifecycle callbacks). `markAsRead()` on an already-read notification is a no-op and on an archived one throws; `archive()` on an already-archived one is a no-op. `ApiException` is the abstract base (`getErrorCode()`, `getHttpStatus()`); `InvalidNotificationTransition` extends it with code `INVALID_NOTIFICATION_TRANSITION` and status 409. `Delivery::markSending()` sets `sendingAt` and increments `attempts`; `reclaimStale()` requires `SENDING` and sets `RETRY_WAITING` with `nextAttemptAt = now`, `lastError = 'Reclaimed after timeout'`, incrementing attempts; `scheduleRetry()` requires `SENDING`. The `Notification → Delivery → DeliveryAttempt` associations use `cascade: ['remove']` and `onDelete: 'CASCADE'`, so purge deletes the whole chain.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php bin/phpunit tests/Unit/Entity`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Enum services/notification-service/src/Entity services/notification-service/src/Traits services/notification-service/tests/Unit/Entity
git commit -m "feat(notification): add domain entities and state machines"
```

---

### Task 3: Schema Migration, Repositories, and API Foundation

**Files:**
- Create: `migrations/Version20261116000000.php`
- Create: `src/Repository/{NotificationRepository,DeliveryRepository,DeliveryAttemptRepository,NotificationPreferenceRepository,NotificationTemplateRepository,ProcessedEventRepository,SettingRepository}.php`, `src/Dto/{NotificationFilter,DeliveryFilter}.php`
- Create: `src/Utils/{ApiResponse,Pagination}.php`, `src/Exceptions/{NotificationNotFound,DeliveryNotFound,TemplateNotFound,InvalidDeliveryTransition}.php`, `src/EventListener/ApiExceptionListener.php`
- Test: `tests/Integration/Repository/NotificationRepositoryTest.php`

**Interfaces:**
- `ApiResponse::success(mixed $data, ?Pagination $pagination = null, ?array $meta = null): self`
- `Pagination::create(int $page, int $pageSize, int $totalItems, Closure $urlForPage): self`
- `NotificationRepository::searchForRecipient(int $recipientId, NotificationFilter $filter): array{0: Notification[], 1: int}`
- `NotificationRepository::countUnread(int $recipientId): int`
- `NotificationRepository::findForRecipient(int $recipientId, Uuid $id): ?Notification`
- `NotificationRepository::markAllRead(int $recipientId): int`, `archiveAllRead(int $recipientId): int`, `purgeArchivedBefore(DateTimeImmutable $before): int`
- `NotificationFilter::__construct(int $page=1, int $pageSize=20, ?NotificationStatus $status=null, ?string $type=null, ?Severity $severity=null, ?string $search=null)`
- `DeliveryRepository::search(DeliveryFilter $filter): array{0: Delivery[], 1: int}`, `findWithAttempts(Uuid $id): ?Delivery`
- `DeliveryFilter::__construct(int $page=1, int $pageSize=20, ?DeliveryStatus $status=null, ?Channel $channel=null, ?string $type=null, ?string $search=null)`; `search` matches `recipientEmail` or the parent notification type; `type` filters through the notification join.
- `SettingRepository::all(): Setting[]`, `findByKey(string $key): ?Setting`, `get(string $key, string $default): string`

- [ ] **Step 1: Write the failing repository test**

```php
public function testInboxSearchFiltersHiddenItemsAndCountsUnread(): void
public function testDedupKeyConflictIsDetectable(): void
public function testSettingGetFallsBackToDefault(): void
```
Seed two visible notifications (one unread) and one `visibleInApp=false` notification; assert the list returns only visible rows, `countUnread` is 1, and `status=unread` filtering works.

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/phpunit tests/Integration/Repository/NotificationRepositoryTest.php`
Expected: FAIL — repositories/migration missing.

- [ ] **Step 3: Implement the migration and repositories**

Write the migration with the DBAL schema API: tables and indices exactly as the spec lists (`notifications` index on `(recipient_id, visible_in_app, status, created_at)`, unique `(recipient_id, dedup_key)`, deliveries indices on `(status,next_attempt_at)`, `(status,sending_at)`, `(recipient_email)`, unique preference and template keys, UUID template PK, `processed_events.event_id` PK). Add `NotificationFilter` and `DeliveryFilter` under `src/Dto/`. Repositories extend `ServiceEntityRepository`. The inbox query joins nothing and always adds `n.visibleInApp = true`; `search` matches `title`/`body` with `LIKE` on lowercased text. Copy `ApiResponse`/`Pagination` from product-service, adding the optional `meta` key. Copy `ApiExceptionListener`.

- [ ] **Step 4: Run repository tests and schema validation**

Run:
```bash
php bin/phpunit tests/Integration/Repository/NotificationRepositoryTest.php
php bin/console doctrine:schema:validate --skip-sync
```
Expected: PASS; mapping valid.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/migrations services/notification-service/src/Repository services/notification-service/src/Dto services/notification-service/src/Utils services/notification-service/src/Exceptions services/notification-service/src/EventListener services/notification-service/tests/Integration/Repository
git commit -m "feat(notification): add schema, repositories and API foundation"
```

---

### Task 4: Authentication Boundary and Internal User Directory

**Files:**
- Modify: `services/authentication-service/src/Repository/UserRepository.php`
- Create: `services/authentication-service/src/Controller/ApiInternalUsersController.php`
- Modify: `services/authentication-service/config/packages/security.yaml`, `services/authentication-service/.env`, `services/authentication-service/src/DataFixtures/UserFixtures.php`, `docker-compose.auth.yml`
- Modify: `infrastructure/gateway/nginx.conf` (block internal path)
- Create: `src/Security/{Caller,Recipient,AuthServiceClient,CallerResolver}.php`
- Create: `src/Exceptions/{UnauthenticatedException,ForbiddenException,AuthServiceUnavailableException}.php`
- Test: `services/authentication-service/tests/Controller/ApiInternalUsersControllerTest.php`, `services/notification-service/tests/Unit/Security/AuthServiceClientTest.php`, `tests/Unit/Security/CallerResolverTest.php`

**Interfaces:**
- Auth endpoint: `GET /api/authenticator/internal/users?role=ACCOUNTANT` or `?ids=1,2`, header `X-INTERNAL-TOKEN`, response `{"data":[{"id","email","fullName","roles"}]}`.
- `Caller::__construct(int $id, string $email, string $fullName, array $roles)`, `hasRole(string $role): bool`
- `Recipient` has the same shape.
- `AuthServiceClient::authenticate(string $token): ?Caller`
- `AuthServiceClient::resolveAudience(array $audience): Recipient[]` (`['role' => string]` or `['userIds' => int[]]`)
- `CallerResolver::resolve(Request $request): Caller`, `requireAdmin(Caller $caller): void`

- [ ] **Step 1: Write failing tests**

Auth service:
```php
public function testRejectsMissingOrWrongToken(): void // 401
public function testFiltersVerifiedUsersByRole(): void // returns only ROLE_ACCOUNTANT
public function testFiltersByIds(): void
```
Notification service:
```php
public function testAuthenticateReturnsCallerFromMeEndpoint(): void // MockHttpClient
public function testAuthenticateReturnsNullOn401(): void
public function testResolveAudienceNormalizesRolePrefix(): void // ACCOUNTANT -> ROLE_ACCOUNTANT
public function testResolveAudienceThrowsUnavailableOnHttpFailure(): void
public function testCallerResolverRejectsMissingToken(): void // UnauthenticatedException
```

- [ ] **Step 2: Run to verify failure**

Run:
```bash
cd services/authentication-service && php bin/phpunit tests/Controller/ApiInternalUsersControllerTest.php
cd ../notification-service && php bin/phpunit tests/Unit/Security
```
Expected: FAIL.

- [ ] **Step 3: Implement both sides**

Auth service: add `UserRepository::findVerifiedByRole(string $role): array` and `findVerifiedByIds(array $ids): array` (filter in PHP so roles-as-JSON works on MySQL and SQLite tests). Controller validates `hash_equals($this->internalToken, (string) $request->headers->get('X-INTERNAL-TOKEN'))`, returns 401 otherwise, and 422 when neither/both `role` and `ids` are supplied. Add `{ path: ^/api/authenticator/internal/, roles: PUBLIC_ACCESS }` before `^/api` in `security.yaml`. Inject `%env(AUTH_INTERNAL_TOKEN)%` via `#[Autowire]`. Update fixtures: demo/test users verified with full names, add `admin@example.com` (`ROLE_ADMIN`), `accountant@example.com` (`ROLE_ACCOUNTANT`), documented password `ChangeMe123!`. Add `AUTH_INTERNAL_TOKEN` to auth `.env` and compose. Gateway: add `location /api/authenticator/internal/ { return 404; }` before the authenticator location.

Notification service: `AuthServiceClient` uses `HttpClientInterface` and `CacheInterface`; cache `me` results by `hash('sha256',$token)` for 60s. `authenticate` returns null on 401/403, throws `AuthServiceUnavailableException` on 5xx/transport errors. `resolveAudience` calls the internal endpoint with `X-INTERNAL-TOKEN`, maps `data` to `Recipient`, throws `AuthServiceUnavailableException` on non-2xx/transport. `CallerResolver` reads `X-AUTH-TOKEN`.

- [ ] **Step 4: Run the tests**

Run both suites again; expected PASS.

- [ ] **Step 5: Commit**

```bash
git add services/authentication-service services/notification-service/src/Security services/notification-service/src/Exceptions services/notification-service/tests/Unit/Security infrastructure/gateway/nginx.conf docker-compose.auth.yml
git commit -m "feat(auth): add internal user directory and caller resolution"
```

---

### Task 5: Templates, Settings, Preferences, and Rendering

**Files:**
- Create: `src/Service/{TemplateRenderer,SettingProvider}.php`
- Create: `src/DataFixtures/V1/{TemplateFixtures,SettingFixtures,PreferenceFixtures,NotificationFixtures}.php`
- Test: `tests/Unit/Service/TemplateRendererTest.php`, `tests/Integration/Service/SettingProviderTest.php`

**Interfaces:**
- `TemplateRenderer::render(string $type, Channel $channel, array $params): array{subject: string, body: string}`
- `SettingProvider::string(string $key): string`, `int(string $key): int`
- `TemplateRenderer` falls back `(type, channel, locale)` → `(type, channel, default)` → built-in `GenericNotificationTemplate`.

- [ ] **Step 1: Write failing renderer tests**

```php
public function testReplacesPlaceholdersFromParams(): void
public function testFallsBackToDefaultLocale(): void
public function testFallsBackToBuiltInTemplateWhenMissing(): void
public function testMissingPlaceholderDoesNotThrowAndLeavesKeyOut(): void
public function testHtmlInParamsIsEscapedInEmailBody(): void
```
Seed: `payment.installment.overdue`/email/`en` subject `Invoice {{ invoiceNumber }} overdue`, body `<p>{{ partyName }}</p>`. Assert exact rendered strings and escaping.

- [ ] **Step 2: Run to verify failure**

Run: `php bin/phpunit tests/Unit/Service/TemplateRendererTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement renderer and fixtures**

Renderer replaces `{{ key }}` with `htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')` for email and the raw string for in-app titles; unknown placeholders become empty strings; wraps email body in a minimal HTML shell. `SettingProvider` reads through `SettingRepository` and caches the full map for 60 seconds. Fixtures seed the settings from the spec, templates for `payment.installment.overdue`, `invoice.issued`, `order.confirmed` in both channels (`en`), a disabled example preference, and 6 notifications for user 1.

- [ ] **Step 4: Run tests**

Run: `php bin/phpunit tests/Unit/Service tests/Integration/Service`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Service services/notification-service/src/DataFixtures services/notification-service/tests/Unit/Service services/notification-service/tests/Integration/Service
git commit -m "feat(notification): add templates, settings, preferences and rendering"
```

---

### Task 6: Event Contract, Consumer, and Idempotency

**Files:**
- Create: `src/Message/NotificationRequested.php`, `src/MessageHandler/NotificationRequestedHandler.php`
- Create: `src/Handler/Notification/CreateNotificationsHandler.php`
- Create: `src/Exceptions/InvalidNotificationEvent.php`
- Create: `src/Console/SendNotificationCommand.php`
- Modify: `config/packages/messenger.yaml`, `config/services.yaml`
- Test: `tests/Unit/Message/NotificationRequestedTest.php`, `tests/Integration/Handler/CreateNotificationsHandlerTest.php`

**Interfaces:**
- `NotificationRequested::fromArray(array $payload): self` throws `InvalidNotificationEvent`
- `CreateNotificationsHandler::__invoke(NotificationRequested $event): int` returns created notification count
- `NotificationRequestedHandler::__invoke(NotificationRequested $message): void`
- `SendNotificationCommand` name `app:notifications:send`, options `--type=`, `--role=`, `--user=` (repeatable), `--severity=`, `--link=`, `--param=key=value` (repeatable).

- [ ] **Step 1: Write failing tests**

```php
public function testRejectsMissingAudience(): void
public function testRejectsBothRoleAndUserIds(): void
public function testNormalizesSeverityAndDefaultsToInfo(): void
public function testCreatesNotificationAndQueuedDeliveryInOneTransaction(): void
public function testDuplicateEventIdCreatesNothing(): void
public function testDuplicateDedupKeySkipsOnlyThatRecipient(): void
public function testRoleAudienceCreatesOneNotificationPerRecipient(): void
public function testInAppDisabledEmailEnabledCreatesHiddenNotificationAndDelivery(): void
public function testBothChannelsDisabledCreatesNothing(): void
public function testAuthServiceFailureLeavesNoRows(): void
```
Use a fake `AuthServiceClient` via `services_test.yaml` replacement or a mock; assert counts in the repositories; assert delivery snapshot fields (`recipientEmail`, `subject`, `body`) match the rendered template.

- [ ] **Step 2: Run to verify failure**

Run: `php bin/phpunit tests/Unit/Message tests/Integration/Handler/CreateNotificationsHandlerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the DTO, handler and command**

`NotificationRequested::fromArray` validates required keys, UUID event id, ISO-8601 `occurredAt`, non-empty `source`/`type`, audience XOR, severity, leading-slash link, scalar params. Throw `UnrecoverableMessageHandlingException` from the message handler when `InvalidNotificationEvent` escapes (import Symfony's class), so it lands in the failed/dead-letter transport.

`CreateNotificationsHandler`:
1. `$recipients = $auth->resolveAudience($event->audience)` (before the transaction).
2. `$em->wrapInTransaction(...)`: persist `ProcessedEvent` + `flush()`; catch `UniqueConstraintViolationException` only for the processed-event insert and return 0. Enable DBAL nested transactions with savepoints (`getConnection()->setNestTransactionsWithSavepoints(true)`), then process each recipient inside its own nested transaction: load preferences (default enabled), skip both-disabled; render `in_app`/`email`; create `Notification(..., visibleInApp: inApp)`; create `Delivery` when email enabled and email non-empty; flush. A `UniqueConstraintViolationException` from the `(recipient_id,dedup_key)` index rolls back only that recipient's savepoint and continues with the next one.
3. Return the created count.

Register `App\Message\NotificationRequested` → `notification_events` routing. The console command builds the payload and calls the handler synchronously.

- [ ] **Step 4: Run tests**

Run: `php bin/phpunit tests/Unit/Message tests/Integration/Handler/CreateNotificationsHandlerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Message services/notification-service/src/MessageHandler services/notification-service/src/Handler/Notification services/notification-service/src/Exceptions services/notification-service/src/Console services/notification-service/config services/notification-service/tests
git commit -m "feat(notification): consume NotificationRequested idempotently"
```

---

### Task 7: Inbox API

**Files:**
- Create: `src/Api/V1/Controller/NotificationsController.php`
- Create: `src/Api/V1/Request/ListNotificationsQuery.php`
- Create: `src/Api/V1/Resource/NotificationResource.php`, `src/Api/V1/Mapper/NotificationMapper.php`
- Create: `src/Api/V1/Documentation/{NotificationDocument,NotificationAttributesDocument,PaginationDocument,ErrorDocument,ErrorDetailDocument}.php`
- Test: `tests/Controller/Api/V1/NotificationsControllerTest.php`

**Interfaces:**
- Routes: `GET /notifications`, `GET /notifications/unread-count`, `GET /notifications/{id}`, `POST /notifications/{id}/read`, `POST /notifications/read-all`, `POST /notifications/{id}/archive`, `POST /notifications/archive-read`. Declare static routes (`unread-count`, `read-all`, `archive-read`) before `/{id}` and require `id` to match `[0-9a-f-]{36}`.
- Responses: list has `data`, `pagination`, `meta.unreadCount`; unread count returns `data: {count:int}`; actions return the updated resource or `{updated:int}` for bulk actions.

- [ ] **Step 1: Write failing controller tests**

```php
public function testListsOnlyCallerVisibleNotifications(): void
public function testUnreadCountExcludesHiddenAndArchived(): void
public function testShowReturns404ForAnotherRecipient(): void
public function testMarkReadIsIdempotent(): void
public function testReadAllMarksOnlyVisibleUnread(): void
public function testArchiveUnreadAndArchivedIsIdempotent(): void
public function testMissingTokenReturns401(): void
```
Seed rows through the entity manager; replace `AuthServiceClient` with a fake returning caller id 1.

- [ ] **Step 2: Run to verify failure**

Run: `php bin/phpunit tests/Controller/Api/V1/NotificationsControllerTest.php`
Expected: FAIL (404 routes).

- [ ] **Step 3: Implement controller, DTO, mapper, docs**

Thin controller: resolve caller, call repository/handlers. `ListNotificationsQuery` uses `#[MapQueryString]` with `page=1`, `pageSize=20` (1..100), optional enums; invalid enum → 400. Map to `NotificationResource(id: (string)$n->getId(), attributes: [...])`. Build pagination URLs with `generateUrl`. Add Nelmio attributes/tag `Notifications` and document the envelope, 401/404.

- [ ] **Step 4: Run tests**

Run: `php bin/phpunit tests/Controller/Api/V1/NotificationsControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Api/V1 services/notification-service/tests/Controller
git commit -m "feat(notification): add inbox REST API"
```

---

### Task 8: Preferences API

**Files:**
- Create: `src/Api/V1/Controller/PreferencesController.php`
- Create: `src/Api/V1/Request/{SavePreferencesRequest,PreferenceRequest}.php`
- Create: `src/Api/V1/Resource/{PreferenceResource,PreferenceMatrixResource}.php`, `src/Api/V1/Mapper/PreferenceMapper.php`
- Create: `src/Api/V1/Documentation/{PreferenceDocument,PreferenceEntryDocument}.php`
- Test: `tests/Controller/Api/V1/PreferencesControllerTest.php`

**Interfaces:**
- `GET /preferences` returns `data: [{type, channels: {in_app: bool, email: bool}}]` for every distinct template type, defaulting to enabled.
- `PUT /preferences` accepts `{"preferences":[{"type":"payment.installment.overdue","channel":"email","enabled":false}]}` and returns the refreshed matrix.

- [ ] **Step 1: Write failing tests**

```php
public function testReturnsDefaultEnabledMatrixForTemplateTypes(): void
public function testUpsertsPreferencesForCallerOnly(): void
public function testRejectsUnknownChannel(): void
public function testMissingTokenReturns401(): void
```
Assert another user's rows are not modified and repeated PUT is idempotent.

- [ ] **Step 2: Run to verify failure**

Run: `php bin/phpunit tests/Controller/Api/V1/PreferencesControllerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

Use `#[MapRequestPayload]` and `#[Assert\Valid]` on the preference array; validate channel via enum (`Assert\Choice` or typed enum). Upsert by `(userId,type,channel)`; reject duplicate `(type,channel)` entries in one request with 422. Build the matrix from `NotificationTemplateRepository::findDistinctTypes()` and existing rows.

- [ ] **Step 4: Run tests**

Run: `php bin/phpunit tests/Controller/Api/V1/PreferencesControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Api/V1 services/notification-service/tests/Controller/Api/V1/PreferencesControllerTest.php
git commit -m "feat(notification): add preferences API"
```

---

### Task 9: Admin Deliveries API and Requeue

**Files:**
- Create: `src/Handler/Delivery/RequeueDeliveryHandler.php`
- Create: `src/Api/V1/Controller/AdminDeliveriesController.php`
- Create: `src/Api/V1/Request/ListDeliveriesQuery.php`
- Create: `src/Api/V1/Resource/{DeliveryResource,DeliveryAttemptResource}.php`, `src/Api/V1/Mapper/DeliveryMapper.php`
- Create: `src/Api/V1/Documentation/{DeliveryDocument,DeliveryAttributesDocument,DeliveryAttemptDocument}.php`
- Test: `tests/Controller/Api/V1/AdminDeliveriesControllerTest.php`

**Interfaces:**
- `GET /admin/deliveries` filters `status`, `channel`, `type`, `search` (recipient email / event type).
- `GET /admin/deliveries/{id}` includes `attempts: [{attemptNumber,providerMessageId,error,attemptedAt}]`.
- `POST /admin/deliveries/{id}/requeue` → `RequeueDeliveryHandler::__invoke(Uuid $id): Delivery`.

- [ ] **Step 1: Write failing tests**

```php
public function testRejectsNonAdmin(): void // 403
public function testListsDeliveriesWithFilters(): void
public function testShowIncludesAttempts(): void
public function testRequeueFailedDeliveryResetsState(): void
public function testRequeueSentOrQueuedReturns409(): void
```
Assert `requeue` yields `queued`, `attempts=0`, `lastError=null`, `nextAttemptAt` set, and a `DeliveryAttempt` history row is preserved.

- [ ] **Step 2: Run to verify failure**

Run: `php bin/phpunit tests/Controller/Api/V1/AdminDeliveriesControllerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement controller, mapper, handler**

`CallerResolver::requireAdmin()` first. Use `DeliveryRepository::search`. Requeue throws `InvalidDeliveryTransition` → 409 if status is not `failed`. Mapper includes notification id/type, recipient email, snapshot subject (not body in list; include body in show), timestamps.

- [ ] **Step 4: Run tests**

Run: `php bin/phpunit tests/Controller/Api/V1/AdminDeliveriesControllerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Handler/Delivery services/notification-service/src/Api/V1 services/notification-service/tests/Controller/Api/V1/AdminDeliveriesControllerTest.php
git commit -m "feat(notification): add delivery admin API"
```

---

### Task 10: Admin Templates and Settings API

**Files:**
- Create: `src/Api/V1/Controller/AdminTemplatesController.php`, `src/Api/V1/Controller/AdminSettingsController.php`
- Create: `src/Api/V1/Request/{SaveTemplateRequest,UpdateSettingRequest}.php`
- Create: `src/Api/V1/Resource/{TemplateResource,SettingResource}.php`, `src/Api/V1/Mapper/{TemplateMapper,SettingMapper}.php`
- Create: `src/Api/V1/Documentation/{TemplateDocument,SettingDocument}.php`
- Test: `tests/Controller/Api/V1/AdminTemplatesControllerTest.php`, `tests/Controller/Api/V1/AdminSettingsControllerTest.php`

**Interfaces:**
- `GET/POST /admin/templates`, `GET/PUT/DELETE /admin/templates/{id}` (UUID id).
- `GET /admin/settings`, `PUT /admin/settings/{key}` with `{"value":"..."}`.

- [ ] **Step 1: Write failing tests**

```php
public function testCreateTemplateRejectsDuplicateNaturalKey(): void // 409
public function testUpdateTemplateChangesSubjectAndBody(): void
public function testDeleteTemplateReturns204(): void
public function testUnknownTemplateIdReturns404(): void
public function testUpdateSettingValidatesNumericKeys(): void // retention/attempts positive ints
public function testNonAdminCannotManageTemplatesOrSettings(): void // 403
```

- [ ] **Step 2: Run to verify failure**

Run: `php bin/phpunit tests/Controller/Api/V1/AdminTemplatesControllerTest.php tests/Controller/Api/V1/AdminSettingsControllerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

Admin guard on every action; `SaveTemplateRequest` validates `type` (max 120), channel enum, locale (2..5), subject (max 255), bodyTemplate non-blank. Create catches `UniqueConstraintViolationException` → 409. `UpdateSettingRequest` only allows known keys from `SettingRepository`; numeric keys validate positive integers. Invalidate the settings cache on update.

- [ ] **Step 4: Run tests**

Run the two test files; expected PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Api/V1 services/notification-service/tests/Controller/Api/V1
git commit -m "feat(notification): add template and setting admin API"
```

---

### Task 11: Email Delivery Worker

**Files:**
- Create: `src/Mail/{DeliverySender,EmailDeliverySender,DeliverySendException}.php`
- Create: `src/Handler/Delivery/ProcessDeliveriesHandler.php`
- Create: `src/Console/ProcessDeliveriesCommand.php`
- Modify: `config/services.yaml` (bind `MAILER_DSN`, Mailer from address from settings at call time)
- Test: `tests/Integration/Handler/ProcessDeliveriesHandlerTest.php`

**Interfaces:**
- `DeliverySender::send(Delivery $delivery, string $fromAddress, string $fromName): string` returns provider message id; throws `DeliverySendException` with `isPermanent(): bool`.
- `ProcessDeliveriesHandler::__invoke(int $limit = 50): array{sent:int, retried:int, failed:int}`
- Command `app:deliveries:process --limit=50`.

- [ ] **Step 1: Write failing tests**

Use a fake `DeliverySender` (services_test.yaml) that records calls and throws on demand:
```php
public function testSendsDueDeliveryAndMarksSentWithAttempt(): void
public function testTemporaryFailureSchedulesRetryAndIncrementsAttempts(): void
public function testPermanentFailureMarksFailedImmediately(): void
public function testMaxAttemptsMarksFailed(): void
public function testStaleSendingRowIsReclaimedAndRetried(): void
public function testFutureRetryIsNotClaimed(): void
```
Assert attempt rows (`attemptNumber`, `providerMessageId`/`error`), `nextAttemptAt` backoff grows, and a stale `sending` row with `sendingAt` older than 600s is reclaimed while a fresh one is skipped.

- [ ] **Step 2: Run to verify failure**

Run: `php bin/phpunit tests/Integration/Handler/ProcessDeliveriesHandlerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement sender, handler, command**

`EmailDeliverySender` builds `Email`/`TemplatedEmail` with the delivery's snapshot subject/HTML body, from address/name args, and sends with `MailerInterface::send`; returns `$sentMessage->getMessageId()`. Catch `RfcComplianceException` as permanent; other `TransportExceptionInterface` as temporary.

Handler:
1. Reclaim stale `sending` rows (`sendingAt < now - timeout`) with `reclaimStale()` and flush.
2. Select candidate ids (`status in (queued,retry_waiting)` and `nextAttemptAt <= now`) with a limit. Claim each with an atomic conditional update: `UPDATE deliveries SET status='sending', sending_at=:now, attempts=attempts+1 WHERE id=:id AND status IN ('queued','retry_waiting') AND next_attempt_at <= :now`; proceed only when one row was affected. This is portable to SQLite and avoids locking entirely; the MySQL path may additionally use `FOR UPDATE SKIP LOCKED` for the candidate selection. After a successful claim, load the delivery through the entity manager (or `refresh()` it) so the send path sees `sending`, `sendingAt` and the incremented `attempts`.
3. Send outside the transaction. Persist `DeliveryAttempt` and one of `markSent`/`scheduleRetry`/`markFailed` in a new transaction.
4. Catch per-delivery exceptions, log, continue. Return counts.

Backoff: `min(base * 2^(attempts-1), max)` from settings; permanent failures ignore backoff.

- [ ] **Step 4: Run tests**

Run: `php bin/phpunit tests/Integration/Handler/ProcessDeliveriesHandlerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Mail services/notification-service/src/Handler/Delivery services/notification-service/src/Console services/notification-service/config/services.yaml services/notification-service/tests/Integration/Handler
git commit -m "feat(notification): send queued emails with retries and recovery"
```

---

### Task 12: Purge Command and Retention

**Files:**
- Create: `src/Console/PurgeNotificationsCommand.php`
- Modify: `NotificationRepository::purgeArchivedBefore()`
- Test: `tests/Integration/Console/PurgeNotificationsCommandTest.php`

**Interfaces:**
- Command `app:notifications:purge`; deletes archived notifications older than `notifications.retention_days`, cascading deliveries/attempts.

- [ ] **Step 1: Write the failing test**

```php
public function testPurgesOnlyArchivedNotificationsOlderThanRetention(): void
```
Seed one archived old, one archived recent, one unread old; assert only the first is deleted and its deliveries/attempts are gone.

- [ ] **Step 2: Run to verify failure**

Run: `php bin/phpunit tests/Integration/Console/PurgeNotificationsCommandTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

Use `SettingProvider::int('notifications.retention_days')`, compute `now - days`, delete through DQL or iterate with `EntityManager::remove` so ORM cascades apply. Output `Purged N notification(s).`

- [ ] **Step 4: Run test**

Run: `php bin/phpunit tests/Integration/Console/PurgeNotificationsCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/src/Console/PurgeNotificationsCommand.php services/notification-service/src/Repository/NotificationRepository.php services/notification-service/tests/Integration/Console
git commit -m "feat(notification): purge archived notifications by retention"
```

---

### Task 13: Frontend API Module and Helpers

**Files:**
- Create: `frontend/src/services/notifications-api.tsx`
- Test: `frontend/src/services/notifications-api.test.ts`

**Interfaces:**
- Types: `NotificationStatus`, `NotificationSeverity`, `NotificationChannel`, `AppNotification`, `NotificationAttributes`, `NotificationPagination`, `NotificationsPage` (`items`, `pagination`, `unreadCount`), `NotificationQuery`, `NotificationPreference`, `Delivery`, `DeliveryStatus`, `DeliveryAttempt`, `Template`, `Setting`.
- Functions: `getNotifications`, `getUnreadCount`, `getNotification`, `markNotificationRead`, `markAllNotificationsRead`, `archiveNotification`, `archiveReadNotifications`, `getPreferences`, `savePreferences`, `getDeliveries`, `getDelivery`, `requeueDelivery`, `getTemplates`, `getTemplate`, `createTemplate`, `updateTemplate`, `deleteTemplate`, `getSettings`, `updateSetting`.
- Helpers: `formatRelativeTime(iso: string, now?: Date): string`, `severityLabel`, `statusLabel`.
- Base URL `/api/notification`.

- [ ] **Step 1: Write failing tests**

Follow `products-api.test.ts` (`vi.mock("@/lib/api")`):
```ts
it("maps the list envelope including meta.unreadCount")
it("throws the API error message on an error envelope")
it("encodes filters into query params")
it("posts read/archive/requeue actions")
it("formats relative times for minutes, hours and days")
it("sends preferences as {preferences:[...]}")
```

- [ ] **Step 2: Run to verify failure**

Run: `cd frontend && pnpm test -- notifications-api`
Expected: FAIL — module missing.

- [ ] **Step 3: Implement the module**

Reuse the envelope unwrap pattern from `invoices-api.tsx`; list reads `response.data.meta?.unreadCount ?? 0`; `formatRelativeTime` uses `Intl.RelativeTimeFormat` with thresholds `<60s`, `<60m`, `<24h`, else days.

- [ ] **Step 4: Run tests**

Run: `cd frontend && pnpm test -- notifications-api`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/services/notifications-api.tsx frontend/src/services/notifications-api.test.ts
git commit -m "feat(notification): add frontend API client"
```

---

### Task 14: Header Bell, Inbox Page, and Navigation

**Files:**
- Create: `frontend/src/components/notifications/notification-bell.tsx`
- Create: `frontend/src/pages/dashboard/notifications/page.tsx`
- Modify: `frontend/src/components/dashboard/site-header.tsx`, `frontend/src/components/dashboard/app-sidebar.tsx`, `frontend/src/components/dashboard/nav-user.tsx`, `frontend/src/main.tsx`
- Test: `frontend/src/lib/notifications.test.ts` (pure helpers: severity classes, filters)

**Interfaces:**
- Bell polls `getUnreadCount()` every 60s and on route change; popover shows the 5 latest unread (`getNotifications({status:'unread',pageSize:5})`); item click calls `markNotificationRead`, closes, navigates to `link` or `/dashboard/notifications`.
- Inbox page uses tabs `All/Unread/Archived`, severity select, debounced search, `TablePagination`, empty/loading/error states, Mark all read, Archive read; clicking a row title marks it read and navigates to `link` when present, while explicit Read/Archive actions stay available.
- Routes: `/dashboard/notifications`; sidebar item `Notifications` (`Bell` icon) after Payment plans; header breadcrumb `Notifications`; user-menu Notifications links there.

- [ ] **Step 1: Write failing helper tests**

```ts
it("returns the severity badge class for critical")
it("maps tab values to status query params")
it("builds the inbox query string state from filters")
```
Add the helpers to `frontend/src/lib/notifications.ts`.

- [ ] **Step 2: Run to verify failure**

Run: `cd frontend && pnpm test -- notifications`
Expected: FAIL.

- [ ] **Step 3: Implement the bell, page and navigation**

Reuse `Card`, `Table`, `Tabs`, `Popover`, `Badge`, `Button`, `InputGroup`, `Select`. Show a dot/badge only when the count is > 0. Mark-all uses `markAllNotificationsRead()` and refreshes. Guard against cancelled requests with the same `cancelled` pattern as invoices. Keep the bell accessible (`aria-label="Notifications"`, count in the label).

- [ ] **Step 4: Run tests, lint, build**

Run:
```bash
cd frontend && pnpm test -- notifications && pnpm lint && pnpm build
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/components/notifications frontend/src/pages/dashboard/notifications frontend/src/lib/notifications.ts frontend/src/lib/notifications.test.ts frontend/src/components/dashboard frontend/src/main.tsx
git commit -m "feat(notification): add header bell and inbox UI"
```

---

### Task 15: Preferences Page

**Files:**
- Create: `frontend/src/pages/dashboard/notifications/preferences.tsx`
- Modify: `frontend/src/main.tsx` (route `/dashboard/notifications/preferences`), inbox page header link to preferences.
- Test: `frontend/src/lib/notification-preferences.test.ts`

**Interfaces:**
- Matrix rows from `getPreferences()`; toggles are local state; Save calls `savePreferences()` with only changed rows and disables while saving; success/failure toasts.

- [ ] **Step 1: Write failing helper test**

```ts
it("flattens the matrix into preference entries")
it("returns only changed rows for saving")
```

- [ ] **Step 2: Run to verify failure**

Run: `cd frontend && pnpm test -- notification-preferences`
Expected: FAIL.

- [ ] **Step 3: Implement the page**

Table with type column and IN_APP / EMAIL checkboxes (the project has `Checkbox` but no `Switch`). Explain that disabling both channels suppresses future notifications. Keep the save button disabled when nothing changed.

- [ ] **Step 4: Run tests, lint, build**

Run: `cd frontend && pnpm test -- notification-preferences && pnpm lint && pnpm build`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/pages/dashboard/notifications/preferences.tsx frontend/src/lib/notification-preferences.ts frontend/src/lib/notification-preferences.test.ts frontend/src/main.tsx frontend/src/pages/dashboard/notifications/page.tsx
git commit -m "feat(notification): add preferences UI"
```

---

### Task 16: Admin Deliveries, Templates, and Settings UI

**Files:**
- Create: `frontend/src/pages/dashboard/notifications/admin.tsx`
- Modify: `frontend/src/main.tsx` (route `/dashboard/notifications/admin`), inbox page header "Admin" button visible only for `ROLE_ADMIN` via `useAuth()`.
- Test: `frontend/src/lib/notification-admin.test.ts` (filter/status helpers, template form validation)

**Interfaces:**
- Tabs: Deliveries (status/channel filters, search, requeue failed, expandable attempts), Templates (list + edit dialog for subject/body, create/delete), Settings (key/value rows with save).
- Admin API errors (403) render a permission message rather than a blank page.

- [ ] **Step 1: Write failing tests**

```ts
it("validates a template requires type, locale, subject and body")
it("maps delivery status labels and requeue availability")
```

- [ ] **Step 2: Run to verify failure**

Run: `cd frontend && pnpm test -- notification-admin`
Expected: FAIL.

- [ ] **Step 3: Implement the page**

Use `Tabs`, `Table`, `Dialog`, `Textarea`, `Input`, `Select`, `Button`, `Badge`. Requeue is shown only for `failed`. After a requeue, refresh the list. Settings numeric keys show validation errors before PUT.

- [ ] **Step 4: Run tests, lint, build**

Run: `cd frontend && pnpm test -- notification-admin && pnpm lint && pnpm build`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/pages/dashboard/notifications/admin.tsx frontend/src/lib/notification-admin.ts frontend/src/lib/notification-admin.test.ts frontend/src/main.tsx frontend/src/pages/dashboard/notifications/page.tsx
git commit -m "feat(notification): add delivery/template/settings admin UI"
```

---

### Task 17: Full Verification and Documentation

**Files:**
- Create: `services/notification-service/README.md` (run, endpoints, event contract, demo credentials)
- Modify: `docs/superpowers/specs/2026-11-16-notification-service-design.md` (status → implemented)
- Test: none new

- [ ] **Step 1: Run the backend gates**

```bash
cd services/notification-service
composer cs:check
composer rector:check
composer stan:check
composer unit:test
php bin/console lint:container
php bin/console lint:yaml config/
```
Expected: all exit 0.

- [ ] **Step 2: Run the auth-service and frontend gates**

```bash
cd services/authentication-service && php bin/phpunit
cd ../../frontend && pnpm test && pnpm lint && pnpm build
```
Expected: all pass.

- [ ] **Step 3: Verify compose and gateway wiring**

```bash
cd ../..
docker compose config >/dev/null
docker compose -f docker-compose.notification.yml config >/dev/null
grep -n "api/notification" infrastructure/gateway/nginx.conf
```
Expected: both configs valid; the route exists.

- [ ] **Step 4: Run the demo path**

```bash
docker compose up -d notification-db notification-cache-redis rabbitmq mailpit notification-service
docker compose exec notification-service php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec notification-service php bin/console messenger:setup-transports
docker compose exec notification-service php bin/console app:notifications:send --type=payment.installment.overdue --role=ACCOUNTANT --severity=warning --param=invoiceNumber=F-012
TOKEN=$(curl -s -X POST http://127.0.0.1:8081/api/authenticator/user_login \
  -H 'Content-Type: application/json' \
  -d '{"email":"accountant@example.com","password":"ChangeMe123!"}' | jq -r '.token')
curl -s -H "X-AUTH-TOKEN: $TOKEN" http://127.0.0.1:8081/api/notification/notifications | head
```
Expected: a notification is returned, and Mailpit at `:8025` shows the email after the worker runs.

- [ ] **Step 5: Commit**

```bash
git add services/notification-service/README.md docs/superpowers/specs/2026-11-16-notification-service-design.md
git commit -m "docs(notification): document running and exercising the service"
```
