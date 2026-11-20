# Invoice Service — Design

Date: 2026-11-14
Status: proposed
Source of truth: `diagrams/invoice-service/*.drawio`
Reference implementation: `erp/services/product-service`

## Intent

Build the `invoice-service` inside `erp/services/invoice-service` from the seven
invoice diagrams. The service owns invoices and credit notes, enforces the
invoice state machine, numbers invoices sequentially at issue time, exposes a
REST API, renders invoice PDFs, calculates tax through the Tax Service boundary,
consumes order/payment events, and records domain events in an outbox.

The code follows the structure and tooling of `product-service` so the two
services are operated and tested the same way.

## Goals

- Implement the `Invoice` aggregate with `InvoiceLine` and `CreditNote`, and the
  `DRAFT → ISSUED → PARTIALLY_PAID → PAID` state machine, with `VOIDED` as a
  terminal state.
- Enforce the documented rules:
  - number and dates are assigned at `issue()`;
  - issued invoices are immutable;
  - corrections after issue use credit notes, never status changes;
  - overdue is computed, never stored as a status;
  - payments are idempotent per `paymentId`;
  - overpayments are flagged for review, not applied.
- Expose a versioned REST API under `/api/v1` with the same response envelope,
  validation, pagination, error format, and Nelmio documentation as
  `product-service`.
- Persist invoices, lines, credit notes, payment records, and outbox events in
  the service's own Doctrine database.
- Consume order-ready and payment-received messages through Symfony Messenger.
- Publish outbox events through Messenger with a console command.
- Generate downloadable PDF invoices.
- Ship the same quality gates as `product-service`: PHPUnit, PHPStan, Rector,
  PHP-CS-Fixer, all wired through composer scripts.

## Non-goals

- No changes to `order-service`, `payment-service`, or `tax-service`. Their
  producer-side contracts are out of scope.
- No real payment processing, refunds, or accounts-receivable ledger.
- No multi-currency conversion; currency is a snapshot string from the order.
- No authentication layer of its own. The service assumes the gateway or BFF
  authenticates callers, as the architecture diagram shows.
- No amendments to issued invoices. A credit note is the only correction path.

## Anti-goals

- An invoice status that means "overdue". Overdue must stay a computed value.
- Editing an issued invoice in place.
- Deriving tax locally when the Tax Service is the authority.
- One invoice per order being created twice from a replayed event.
- Applying the same payment twice.

## Constraints

- PHP >= 8.4, Symfony 8.1, Doctrine ORM 3, PHPUnit 13, PHPStan level 5.
- Money is stored in minor units (`int`), never floats.
- UUIDs use `symfony/uid` and Doctrine's `uuid` DBAL type.
- Tests use SQLite, rebuild the schema per test, and never touch a real broker,
  Tax Service, or filesystem outside `var/`.
- `invoice-service` owns its database. No cross-service joins.

## Approaches considered

1. **Layered product-service mirror (chosen).** `Api/V1/{Controller,Request,
   Mapper,Resource,Documentation}`, `Entity`, `Enum`, `Event`, `Handler`,
   `Repository`, `Service`, `Tax`, `Message`, `MessageHandler`, `Console`,
   `Utils`, plus `Build/` tool configs. Domain rules live in the entity; handlers
   orchestrate transactions and outbox writes. This is the only approach that
   satisfies the explicit reference requirement and keeps the two services
   reviewable side by side.
2. **Domain-centric CQRS/hexagonal layout.** Cleaner in the abstract, but it
   would make `invoice-service` diverge from every neighbouring service and
   ignore the requested reference. Rejected.
3. **Single controller plus service class.** Fastest to write, but no room for
   the outbox, tax, numbering, PDF, and message boundaries the diagrams require.
   Rejected.

## Decisions

### Domain model

`Invoice` is the aggregate root.

| Field | Type | Notes |
|---|---|---|
| `id` | `Uuid` | v7, assigned in constructor |
| `number` | `?string` | `null` until issued; unique |
| `type` | `InvoiceType` | `sales` / `purchase`, stored as string |
| `status` | `InvoiceStatus` | `draft` / `issued` / `partially_paid` / `paid` / `voided` |
| `orderId` | `Uuid` | the source order; indexed |
| `activeOrderKey` | `?string` | internal; equals `orderId` while not voided, `null` after void; unique |
| `partyId` | `Uuid` | contact-service id snapshot |
| `partyName` | `string` | snapshot |
| `currency` | `string(3)` | ISO 4217 |
| `issueDate` | `?DateTimeImmutable` | set at issue |
| `dueDate` | `?DateTimeImmutable` | set at issue |
| `amountPaid` | `int` | minor units |
| `overpaymentFlagged` | `bool` | persisted; set when an over-balance payment arrives |
| `version` | `int` | Doctrine `@Version` |
| `createdAt` / `updatedAt` | `DateTimeImmutable` | lifecycle |
| `lines` | `Collection<InvoiceLine>` | cascade persist, orphan removal |
| `creditNotes` | `Collection<CreditNote>` | cascade persist |
| `payments` | `Collection<InvoicePayment>` | idempotency ledger |

Transient `array $events` holds domain events until `pullEvents()` drains them,
matching the class diagram and the `contact-service` precedent.

**Invoice cardinality.** One active invoice per order, as the diagram's
"already invoiced for this orderId?" check states. The first ready event creates
the draft; later partial `order.shipped` / `purchase.order.received` events for
that order are ignored. Because a voided draft must not permanently block the
order, uniqueness is enforced by the internal `activeOrderKey` (nullable, unique)
rather than by `orderId` itself. Creating a draft sets
`activeOrderKey = orderId`; voiding clears it. A replacement invoice for a
voided order gets the same `orderId` and a new `activeOrderKey`. This is
portable across PostgreSQL/MySQL/SQLite because NULLs are distinct in a unique
index.

**Order payload contract.** `invoice-service` defines the full snapshot message
it needs: `orderId`, `type`, `partyId`, `partyName`, `currency`, and lines with
`productId`, `description`, `quantity`, `unitPrice`. The current `order-service`
events do not carry all of those fields, so a future producer change is required.
That change is outside this design and no cross-service HTTP hydration is added.

`InvoiceLine`: `productId` (UUID), `description` snapshot, `quantity`,
`unitPrice`, `taxRateBps`, `taxAmount`, `position`. Line subtotal is
`quantity * unitPrice`; line total is subtotal plus `taxAmount`.

`CreditNote`: auto-increment id, invoice, `number` (`{invoiceNumber}-CN{n}`),
`reason`, `amount` (minor), `issuedAt`.

`InvoicePayment`: supporting entity for the diagram's idempotency rule.
`paymentId` is globally unique, `amount`, `currency`, `accepted` (false when
flagged as an overpayment), `receivedAt`. This is the durable record behind
`applyPayment(paymentId, amount)`.

`CreditNote.number` is unique. Credit notes are numbered per invoice as
`{invoiceNumber}-CN{n}`.

`OutboxEvent`: same shape as `product-service` (`eventType`, `eventVersion`,
JSON `payload`, `createdAt`, `publishedAt`, `markPublished()`).

### State machine

`InvoiceStatus` exposes `canTransitionTo()` and `isFinal()`:

- `draft` → `issued`, `voided`
- `issued` → `partially_paid`, `paid`, `voided` (only while `amountPaid === 0`)
- `partially_paid` → `paid`
- `paid`, `voided` → final

Guarded methods:

- `addLine()`, `updateLine()`, `removeLine()` only in `draft`.
- `issue(number, dueDate)`: draft only, at least one line, sets `issueDate`,
  `dueDate`, `number`, status `issued`, records `InvoiceIssued`.
- `applyPayment(paymentId, amount)`:
  - duplicate `paymentId` on the same invoice → no-op (idempotent);
  - a `paymentId` already recorded on a different invoice → `PAYMENT_ID_CONFLICT`
    (409), never a silent no-op;
  - zero or negative amount → rejected;
  - invoice status `draft` or `voided` → `INVALID_INVOICE_TRANSITION` (409);
  - mismatched currency → `CURRENCY_MISMATCH` (409);
  - amount above remaining balance → record a non-accepted payment, set the
    persisted `overpaymentFlagged = true`, leave the status unchanged. The flag
    survives reload, is exposed by the API, and is cleared only by a future
    manual-review action (out of scope); a later accepted payment does not clear
    it;
  - accepted payment → increase `amountPaid`; status `paid` when balance is zero
    (records `InvoicePaid`), otherwise `partially_paid`.
- `void(reason)`: draft, or issued with no payments; sets `voided`, records
  `InvoiceVoided`.
- `issueCreditNote(amount, reason)`: issued/partially paid/paid; amount must be
  positive and not exceed previously uninvoiced amount; records
  `CreditNoteIssued`. Credit notes do not change status.
- `getTotal()` = sum of line totals; `getCreditedAmount()` = sum of credit
  notes; `getBalance()` = total − paid − credited (may be negative after a
  credit on a paid invoice); `isOverdue()` = due date passed and status is
  `issued` or `partially_paid`.
- `pullEvents()` returns and clears the event list.

Status changes outside these methods are not possible; setters for status,
number, dates, and amounts are not exposed.

### Domain events

All are immutable contract objects with `TYPE`, `VERSION = 1`, and `toOutbox()`:

- `InvoiceCreated(invoiceId, orderId)`
- `InvoiceIssued(invoiceId, number)`
- `InvoicePaid(invoiceId)`
- `InvoiceVoided(invoiceId, reason)`
- `CreditNoteIssued(invoiceId, amount)`

Event type strings: `invoice.created`, `invoice.issued`, `invoice.paid`,
`invoice.voided`, `invoice.credit_note_issued`. Only additive changes to
payloads are allowed.

### Persistence and numbering

- Tables: `invoices`, `invoice_lines`, `credit_notes`, `invoice_payments`,
  `outbox_events`. `invoices.active_order_key` is unique and nullable;
  `invoice_payments.payment_id` is unique and global.
- Number format: `INV-{year}-{000001}` for sales, `BILL-{year}-{000001}` for
  purchase.
- `InvoiceNumberGenerator` uses `symfony/lock` (configurable `LOCK_DSN`,
  `flock` by default) around a query for the highest number of the same type and
  year. The lock is acquired before the issue transaction and released after
  commit. This is the documented exception to "DB row lock" and matches the
  project convention that mutual exclusion uses `symfony/lock`.
- One migration is shipped, written with the DBAL schema API so it runs on
  PostgreSQL in production and SQLite in tests.

### Tax boundary

`TaxCalculatorInterface` returns one `TaxLineResult` (rate in basis points and
amount in minor units) per requested line. `HttpTaxCalculator` calls
`POST {TAX_SERVICE_BASE_URL}/api/v1/tax/calculate` with
`{currency, lines:[{productId, quantity, unitPrice}]}` and expects
`{lines:[{taxRateBps, taxAmount}]}`. Non-2xx and transport errors throw
`TaxServiceUnavailableException` (503). The handler does not catch it; a
consumed message therefore stays queued and is retried by Messenger.

Tests replace `HttpClientInterface` with Symfony's `MockHttpClient`, as
`contact-service` does for its authentication calls, so the adapter itself is
exercised without a network.

### REST API

Base `/api/v1`, response envelope identical to `product-service`:
`{status:"success", data:{id, type:"invoice", attributes:{...}}}` and
`{status:"error", error:{code, message}}`. Pagination uses the same
`Pagination` utility.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/invoices` | paginated search/filter list |
| `POST` | `/invoices` | create a draft from an order snapshot |
| `GET` | `/invoices/{id}` | show one invoice |
| `PUT` | `/invoices/{id}` | replace draft fields/lines |
| `POST` | `/invoices/{id}/issue` | issue (optional `dueDate`) |
| `POST` | `/invoices/{id}/void` | void with a reason |
| `POST` | `/invoices/{id}/credit-notes` | issue a credit note |
| `POST` | `/invoices/{id}/payments` | apply/flag a payment |
| `GET` | `/invoices/{id}/pdf` | download the PDF |

List filters: `page`, `pageSize` (max 100), `status`, `type`, `orderId`,
`partyId`, `search` (number or party name), `overdue`.

`issueDate` is "today" in the application timezone. `dueDate` defaults to
`issueDate + INVOICE_PAYMENT_TERM_DAYS` (default 30, configurable via `.env`);
an explicit `dueDate` must not be before `issueDate`. The default makes tests
deterministic through the container parameter rather than a hardcoded date.

Every creation path — REST or message — records `InvoiceCreated`. A draft
created by an accountant is therefore still announced to the order side.

Error codes: `INVOICE_NOT_FOUND` (404), `INVOICE_ALREADY_EXISTS_FOR_ORDER`
(409), `INVALID_INVOICE_TRANSITION` (409), `INVOICE_NOT_EDITABLE` (409),
`PAYMENT_ID_CONFLICT` (409), `CURRENCY_MISMATCH` (409),
`TAX_SERVICE_UNAVAILABLE` (503). Overpayment is not an error: the response
carries `overpaymentFlagged: true`. Validation errors keep Symfony's 422 shape.

The `POST /invoices` body and the order message share one `InvoiceDraftInput`
DTO: `orderId`, `type`, `partyId`, `partyName`, `currency`, `lines[]`
(`productId`, `description`, `quantity`, `unitPrice`). Tax rate/amount are never
accepted from the caller; they always come from the Tax Calculator.

### Events in and out

Inbound message classes, each handled by an `#[AsMessageHandler]`:

- `OrderReadyForInvoiceMessage(eventType, orderId, type, partyId, partyName,
  currency, lines[], occurredAt)`. `eventType` is `order.shipped` or
  `purchase.order.received`. Handler creates the draft and is idempotent because
  `activeOrderKey` is unique; an event for an already active invoice is ignored.
- `PaymentReceivedMessage(invoiceId, paymentId, amount, currency, occurredAt)`.
  Handler applies the payment; duplicates are no-ops.

Both are routed to `async` and use `in-memory://` in tests.

Durability: `failure_transport: failed` backed by
`doctrine://default?queue_name=failed`, with retries (`max_retries: 5`,
`delay: 1000`, `multiplier: 2`, `max_delay: 60000`). Tax Service failures,
malformed payloads, and illegal transitions throw and therefore retry before
landing on the failure transport; they are never silently dropped. Tax failures
happen before any write, so a retry cannot duplicate an invoice.

Wire format: the transport uses Symfony's serializer with JSON, not the default
PHP serializer. Messages carry a `type` header so another service can consume
the `invoice.*` events without sharing PHP classes. The same JSON contract is
used for both inbound and outbound messages.

Outbound: handlers persist outbox rows in the same transaction as the state
change. `app:outbox:publish` reads unpublished rows, dispatches each as an
`OutboxEventMessage(eventType, eventVersion, payload)` on the async transport,
and marks the row published. The command is safe to re-run; failures leave the
row unpublished.

### PDF

`InvoicePdfRenderer` renders `templates/invoice/pdf.html.twig` through Twig and
converts the HTML with `dompdf/dompdf`. The endpoint returns
`application/pdf`, `Content-Disposition: attachment; filename="{number|id}.pdf"`.
Draft invoices render with a `DRAFT` watermark in the template only; the entity
is untouched.

### Package additions

Runtime: `ext-dom`, `ext-mbstring`, `symfony/uid`, `symfony/messenger`,
`symfony/amqp-messenger`, `symfony/doctrine-messenger`, `symfony/http-client`,
`symfony/lock`, `symfony/cache`, `symfony/serializer`, `symfony/validator`,
`symfony/property-access`, `symfony/property-info`, `symfony/twig-bundle`,
`nelmio/api-doc-bundle`, `phpdocumentor/reflection-docblock`,
`phpstan/phpdoc-parser`, `dompdf/dompdf`.

Dev: `phpunit/phpunit`, `phpstan/phpstan`, `phpstan/phpstan-doctrine`,
`friendsofphp/php-cs-fixer`, `rector/rector`, `symfony/browser-kit`,
`symfony/css-selector`, `symfony/maker-bundle`.

Composer scripts copied verbatim from `product-service`: `cs:check`, `cs:fix`,
`rector:check`, `rector:run`, `stan:fix`, `stan:check`, `unit:test`,
`security:code`, `security:dependencies`.

### Testing plan

Mirror `product-service` test style (schema rebuild per test, SQLite, real
HTTP kernel):

- `tests/Unit/Entity/InvoiceTest.php`: transitions, totals, balance, overdue,
  immutability, duplicate payment, overpayment flag, credit notes, event
  pull-once.
- `tests/Unit/Enum/InvoiceStatusTest.php`: allowed and forbidden transitions.
- `tests/Controller/Api/V1/InvoiceControllerTest.php`: create/show/list/update,
  issue, void, payments, credit note, duplicate active order, re-invoice after
  void, 404/409/422, PDF bytes.
- `tests/Integration/Repository/InvoiceRepositoryTest.php`: filters,
  pagination, overdue.
- `tests/MessageHandler/OrderReadyForInvoiceMessageHandlerTest.php`: creates a
  draft + outbox row; duplicate active order is a no-op; a voided order is
  re-invoiceable; tax failure throws.
- `tests/MessageHandler/PaymentReceivedMessageHandlerTest.php`: applies once,
  duplicate ignored, overpayment flagged and persisted, draft/voided/currency
  mismatch/payment-id-reuse rejected.
- `tests/Console/PublishOutboxCommandTest.php`: publishes pending rows and
  leaves failures unpublished.

All tests run through `composer unit:test`; `stan:check`, `cs:check`, and
`rector:check` must pass too.

## What is left to the builder

- Exact wording of the PDF template and invoice document layout.
- Exact Tax Service endpoint names beyond the adapter contract above.
- Whether `overdue` should be exposed as a filter only or also a dedicated
  endpoint; the design exposes it as a list filter.
- Production `LOCK_DSN` and broker topology; defaults are `flock` and the AMQP
  fanout exchange `invoice_events`.
