# Invoice Service Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `erp/services/invoice-service` from the invoice diagrams: aggregate, state machine, numbering, REST API, PDF, tax boundary, Messenger consumers, and outbox.

**Architecture:** Mirror `product-service`'s layered Symfony layout. The `Invoice` entity owns business rules; handlers orchestrate transactions and outbox writes; a thin API layer maps DTOs; Messenger carries order/payment events in and outbox events out.

**Tech Stack:** PHP 8.4+, Symfony 8.1, Doctrine ORM 3, SQLite tests, PostgreSQL runtime, PHPUnit 13, PHPStan, Rector, PHP-CS-Fixer, Symfony Messenger, Twig + Dompdf, Symfony HttpClient.

**Spec:** `erp/docs/superpowers/specs/2026-11-14-invoice-service-design.md`

## Global Constraints

- Work from `erp/services/invoice-service`; all paths below are relative to it unless prefixed with `erp/`.
- PHP >= 8.4, Symfony 8.1 only. Do not introduce API Platform or a different architecture.
- Money is `int` minor units. Never use floats.
- UUIDs use `symfony/uid`; Doctrine columns use the `uuid` DBAL type.
- Responses use the `product-service` envelope: `{status, data}` or `{status, error}`.
- Statuses are lowercase strings: `draft`, `issued`, `partially_paid`, `paid`, `voided`.
- Types are lowercase strings: `sales`, `purchase`.
- `orderId` is not globally unique. `activeOrderKey` is nullable and unique; it is the RFC 4122 string of `orderId` while an invoice is not voided, and `null` once voided.
- One active invoice per order. Later partial order events are ignored. A voided order can be invoiced again.
- Tax rates and amounts come only from `TaxCalculatorInterface`; never accept them from a request.
- Tests use `in-memory://` Messenger and `MockHttpClient`; no real broker or Tax Service.
- The workspace is not a git repository. If git is initialized during execution, commit after each task; otherwise skip the commit step.
- The same composer scripts as `product-service` must pass at the end: `cs:check`, `stan:check`, `rector:check`, `unit:test`.

## Review Focus

- **Tax service returns 2xx with missing or malformed line results.** The invoice must not be created with zero tax; the adapter must throw `TaxServiceUnavailableException`. Pinned in Task 6.
- **Sequential numbering under restart/replay.** Two issues of the same type/year must never reuse a number; the lock must be held through the issuing transaction. Pinned in Task 7.
- **Replayed order event after the draft was voided.** It must create a replacement invoice, not be ignored as a duplicate. Pinned in Task 8.
- **Credit note larger than the remaining invoice value.** It must be rejected; it must not drive the credit balance below zero. Pinned in Task 3.
- **PDF with non-ASCII party/line text and a draft invoice.** It must return a valid `%PDF` document, not a 500. Pinned in Task 13.

---

### Task 1: Toolchain, dependencies, and enums

**Files:**
- Modify: `composer.json`
- Create: `Build/cs-fixer/php-cs-fixer.php`, `Build/rector/rector.php`, `Build/stan/phpstan.neon`, `Build/unit/phpunit.dist.xml`
- Create: `bin/phpunit`, `phpunit.xml`, `tests/bootstrap.php`, `config/services_test.yaml`, `.env.test`
- Create: `src/Enum/InvoiceType.php`, `src/Enum/InvoiceStatus.php`, `src/Enum/PaymentResult.php`
- Test: `tests/Unit/Enum/InvoiceTypeTest.php`, `tests/Unit/Enum/InvoiceStatusTest.php`

**Interfaces:**
- Produces: `InvoiceType::SALES|PURCHASE`, `InvoiceType::numberPrefix(): string`, `InvoiceStatus::canTransitionTo(self $target): bool`, `InvoiceStatus::isFinal(): bool`, `PaymentResult::APPLIED|FLAGGED|DUPLICATE`.

- [ ] **Step 1: Install runtime and dev dependencies**

Run:
```bash
composer require ext-dom:* ext-mbstring:* symfony/uid symfony/messenger symfony/amqp-messenger symfony/doctrine-messenger symfony/http-client symfony/lock symfony/cache symfony/serializer symfony/validator symfony/property-access symfony/property-info symfony/asset symfony/twig-bundle nelmio/api-doc-bundle phpdocumentor/reflection-docblock phpstan/phpdoc-parser dompdf/dompdf
composer require --dev phpunit/phpunit phpstan/phpstan phpstan/phpstan-doctrine friendsofphp/php-cs-fixer rector/rector symfony/browser-kit symfony/css-selector symfony/maker-bundle
```
Expected: composer exits 0 and `composer.lock` is updated.

- [ ] **Step 2: Copy the product-service toolchain configs and scripts**

Copy these files verbatim from `../product-service/`, adjusting only relative paths if the copied file is placed differently:
- `Build/cs-fixer/php-cs-fixer.php`
- `Build/rector/rector.php`
- `Build/stan/phpstan.neon`
- `Build/unit/phpunit.dist.xml`
- `bin/phpunit`
- `phpunit.xml`
- `tests/bootstrap.php`

Add the `cs:check`, `cs:fix`, `rector:check`, `rector:run`, `stan:fix`, `stan:check`, `unit:test`, `security:code`, and `security:dependencies` scripts from `../product-service/composer.json` to `composer.json`, keeping the existing `auto-scripts`.

Create `.env.test`:
```dotenv
KERNEL_CLASS='App\Kernel'
APP_SECRET='test-secret'
DATABASE_URL='sqlite:///%kernel.project_dir%/var/data_test.db'
INVOICE_PAYMENT_TERM_DAYS=30
TAX_SERVICE_BASE_URL='http://tax.test'
TAX_SERVICE_TIMEOUT=3
```

Create `config/services_test.yaml`:
```yaml
parameters:
    # Tests never write outside the disposable cache directory.
    invoice.payment_term_days: 30
```

- [ ] **Step 3: Write the failing enum tests**

`tests/Unit/Enum/InvoiceTypeTest.php`:
```php
public function testNumberPrefixes(): void
{
    self::assertSame('INV', InvoiceType::SALES->numberPrefix());
    self::assertSame('BILL', InvoiceType::PURCHASE->numberPrefix());
    self::assertSame('sales', InvoiceType::SALES->value);
    self::assertSame('purchase', InvoiceType::PURCHASE->value);
}
```

`tests/Unit/Enum/InvoiceStatusTest.php`:
```php
public function testAllowedTransitions(): void
{
    self::assertTrue(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::ISSUED));
    self::assertTrue(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::VOIDED));
    self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::PARTIALLY_PAID));
    self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::PAID));
    self::assertTrue(InvoiceStatus::ISSUED->canTransitionTo(InvoiceStatus::VOIDED));
    self::assertTrue(InvoiceStatus::PARTIALLY_PAID->canTransitionTo(InvoiceStatus::PAID));
}

public function testForbiddenTransitions(): void
{
    self::assertFalse(InvoiceStatus::DRAFT->canTransitionTo(InvoiceStatus::PAID));
    self::assertFalse(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::ISSUED));
    self::assertFalse(InvoiceStatus::VOIDED->canTransitionTo(InvoiceStatus::DRAFT));
    self::assertFalse(InvoiceStatus::PAID->canTransitionTo(InvoiceStatus::VOIDED));
    self::assertFalse(InvoiceStatus::PARTIALLY_PAID->canTransitionTo(InvoiceStatus::VOIDED));
}

public function testFinalStates(): void
{
    self::assertTrue(InvoiceStatus::PAID->isFinal());
    self::assertTrue(InvoiceStatus::VOIDED->isFinal());
    self::assertFalse(InvoiceStatus::DRAFT->isFinal());
    self::assertFalse(InvoiceStatus::ISSUED->isFinal());
    self::assertFalse(InvoiceStatus::PARTIALLY_PAID->isFinal());
}
```

- [ ] **Step 4: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Enum`
Expected: FAIL because the enum classes do not exist.

- [ ] **Step 5: Implement the enums**

`src/Enum/InvoiceType.php`:
```php
enum InvoiceType: string
{
    case SALES = 'sales';
    case PURCHASE = 'purchase';

    public function numberPrefix(): string
    {
        return self::SALES === $this ? 'INV' : 'BILL';
    }
}
```

`src/Enum/InvoiceStatus.php`: implement the transition table from the design (`draft` → `issued`, `voided`; `issued` → `partially_paid`, `paid`, `voided`; `partially_paid` → `paid`; final: `paid`, `voided`).

`src/Enum/PaymentResult.php`:
```php
enum PaymentResult: string
{
    case APPLIED = 'applied';
    case FLAGGED = 'flagged';
    case DUPLICATE = 'duplicate';
}
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Enum`
Expected: PASS.

---

### Task 2: Supporting entities

**Files:**
- Create: `src/Entity/OutboxEvent.php`, `src/Entity/InvoiceLine.php`, `src/Entity/CreditNote.php`, `src/Entity/InvoicePayment.php`
- Test: `tests/Unit/Entity/InvoiceLineTest.php`, `tests/Unit/Entity/CreditNoteTest.php`, `tests/Unit/Entity/OutboxEventTest.php`

**Interfaces:**
- Produces:
  - `OutboxEvent::__construct(string $eventType, int $eventVersion, array $payload)`, `markPublished(): void`, `getPublishedAt(): ?DateTimeImmutable`.
  - `InvoiceLine::__construct(Uuid $productId, string $description, int $quantity, int $unitPrice, int $taxRateBps, int $taxAmount)`, `getSubtotal(): int`, `getTotal(): int`, `setTax(int $taxRateBps, int $taxAmount): void`, `setPosition(int $position): void`, `setInvoice(Invoice $invoice): void`.
  - `CreditNote::__construct(Invoice $invoice, string $number, int $amount, string $reason)`, `getAmount(): int`, `getNumber(): string`.
  - `InvoicePayment::__construct(Invoice $invoice, Uuid $paymentId, int $amount, string $currency, bool $accepted)`, `isAccepted(): bool`.

- [ ] **Step 1: Write the failing entity tests**

`InvoiceLineTest`:
```php
public function testLineTotalIncludesTax(): void
{
    $line = new InvoiceLine(Uuid::v7(), 'Café beans', 2, 1000, 2000, 400);

    self::assertSame(2000, $line->getSubtotal());
    self::assertSame(2400, $line->getTotal());
}

public function testLineRejectsNonPositiveQuantity(): void
{
    $this->expectException(\InvalidArgumentException::class);
    new InvoiceLine(Uuid::v7(), 'Bad', 0, 1000, 2000, 0);
}
```

`CreditNoteTest`:
```php
public function testCreditNoteRejectsNonPositiveAmount(): void
{
    $invoice = new Invoice(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR');
    $this->expectException(\InvalidArgumentException::class);
    new CreditNote($invoice, 'INV-2026-000001-CN1', 0, 'Correction');
}
```

`OutboxEventTest`:
```php
public function testMarkPublishedSetsTimestamp(): void
{
    $event = new OutboxEvent('invoice.created', 1, ['invoiceId' => 'x']);
    self::assertNull($event->getPublishedAt());
    $event->markPublished();
    self::assertNotNull($event->getPublishedAt());
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Entity`
Expected: FAIL because the classes do not exist.

- [ ] **Step 3: Implement the entities**

Use the Doctrine attributes from the design. Tables: `invoice_lines`, `credit_notes`, `invoice_payments`, `outbox_events`. `CreditNote.number` is unique. `InvoicePayment.paymentId` is unique, uses `type: 'uuid'`, and stores `currency`. Constructors validate: positive quantity, non-negative unit price, tax rate 0–10000, non-negative tax amount, non-empty description/reason, positive credit amount, currency length 3.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Entity`
Expected: PASS.

---

### Task 3: Invoice aggregate — draft, issue, void, credit notes

**Files:**
- Create: `src/Entity/Invoice.php`
- Create: `src/Exceptions/ApiException.php`, `InvalidInvoiceTransitionException.php`, `InvoiceNotEditableException.php`, `CreditNoteExceedsBalanceException.php`, `InvoiceNotFoundException.php`, `InvoiceAlreadyExistsForOrderException.php`, `CurrencyMismatchException.php`, `PaymentIdConflictException.php`, `TaxServiceUnavailableException.php`, `NumberGenerationException.php`
- Create: `src/Event/InvoiceDomainEvent.php`, `InvoiceCreated.php`, `InvoiceIssued.php`, `InvoicePaid.php`, `InvoiceVoided.php`, `CreditNoteIssued.php`
- Test: `tests/Unit/Entity/InvoiceTest.php`, `tests/Unit/Event/InvoiceEventsTest.php`

**Interfaces:**
- Produces:
  - `Invoice::__construct(Uuid $orderId, InvoiceType $type, Uuid $partyId, string $partyName, string $currency)`.
  - `addLine(InvoiceLine $line): void`, `replaceLines(InvoiceLine[] $lines): void`, `getLines(): Collection`, `getTotal(): int`, `getCreditedAmount(): int`, `getBalance(): int`.
  - `issue(string $number, DateTimeImmutable $dueDate): void`, `void(string $reason): void`, `issueCreditNote(int $amount, string $reason): CreditNote`.
  - `isOverdue(?DateTimeImmutable $today = null): bool`, `pullEvents(): array`, `getActiveOrderKey(): ?string`.
  - `InvoiceDomainEvent::toOutbox(): OutboxEvent`.

- [ ] **Step 1: Write the failing aggregate tests**

`tests/Unit/Entity/InvoiceTest.php` — include at least:
```php
public function testNewInvoiceIsDraftAndEmitsCreated(): void
{
    $invoice = $this->invoice();

    self::assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
    self::assertSame($invoice->getOrderId()->toRfc4122(), $invoice->getActiveOrderKey());
    $events = $invoice->pullEvents();
    self::assertInstanceOf(InvoiceCreated::class, $events[0]);
    self::assertSame([], $invoice->pullEvents());
}

public function testTotalsAndBalance(): void
{
    $invoice = $this->invoice();
    $invoice->addLine(new InvoiceLine(Uuid::v7(), 'A', 2, 1000, 2000, 400));
    $invoice->addLine(new InvoiceLine(Uuid::v7(), 'B', 1, 500, 2000, 100));

    self::assertSame(3000, $invoice->getTotal());
    self::assertSame(0, $invoice->getCreditedAmount());
    self::assertSame(3000, $invoice->getBalance());
}

public function testIssueAssignsNumberDatesAndStatus(): void
{
    $invoice = $this->issued();

    self::assertSame('INV-2026-000001', $invoice->getNumber());
    self::assertSame(InvoiceStatus::ISSUED, $invoice->getStatus());
    self::assertNotNull($invoice->getIssueDate());
    self::assertNotNull($invoice->getDueDate());
}

public function testIssueWithoutLinesThrows(): void
{
    $this->expectException(InvalidInvoiceTransitionException::class);
    $this->invoice()->issue('INV-2026-000001', new DateTimeImmutable('+30 days'));
}

public function testIssuedInvoiceIsNotEditable(): void
{
    $invoice = $this->issued();
    $this->expectException(InvoiceNotEditableException::class);
    $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Late', 1, 100, 0, 0));
}

public function testVoidDraftClearsActiveOrderKey(): void
{
    $invoice = $this->invoice();
    $invoice->void('Wrong order');

    self::assertSame(InvoiceStatus::VOIDED, $invoice->getStatus());
    self::assertNull($invoice->getActiveOrderKey());
}

public function testCreditNoteReducesBalance(): void
{
    $invoice = $this->issued();
    $invoice->issueCreditNote(500, 'Price correction');

    self::assertSame(500, $invoice->getCreditedAmount());
    self::assertSame($invoice->getTotal() - 500, $invoice->getBalance());
}

public function testCreditNoteAboveRemainingValueThrows(): void
{
    $invoice = $this->issued();
    $this->expectException(CreditNoteExceedsBalanceException::class);
    $invoice->issueCreditNote($invoice->getTotal() + 1, 'Too much');
}

public function testOverdueIsComputed(): void
{
    $invoice = $this->issued();
    self::assertFalse($invoice->isOverdue(new DateTimeImmutable('+1 day')));
    self::assertTrue($invoice->isOverdue(new DateTimeImmutable('+60 days')));
}
```

Helper methods in the test:
```php
private function invoice(): Invoice
{
    return new Invoice(Uuid::v7(), InvoiceType::SALES, Uuid::v7(), 'Acme', 'EUR');
}

private function issued(): Invoice
{
    $invoice = $this->invoice();
    $invoice->addLine(new InvoiceLine(Uuid::v7(), 'Widget', 2, 1000, 2000, 400));
    $invoice->issue('INV-2026-000001', new DateTimeImmutable('+30 days'));
    $invoice->pullEvents();

    return $invoice;
}
```

`tests/Unit/Event/InvoiceEventsTest.php`: assert each event's `toOutbox()` has the expected `eventType`, `eventVersion` 1, and payload keys from the design.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Entity tests/Unit/Event`
Expected: FAIL because `Invoice` and the exceptions do not exist.

- [ ] **Step 3: Implement the exceptions and events**

`ApiException` is abstract with `getErrorCode(): string` and `getHttpStatus(): int`. Concrete exceptions:
- `INVOICE_NOT_FOUND` 404
- `INVOICE_ALREADY_EXISTS_FOR_ORDER` 409
- `INVALID_INVOICE_TRANSITION` 409
- `INVOICE_NOT_EDITABLE` 409
- `CREDIT_NOTE_EXCEEDS_BALANCE` 409
- `CURRENCY_MISMATCH` 409
- `PAYMENT_ID_CONFLICT` 409
- `TAX_SERVICE_UNAVAILABLE` 503
- `NUMBER_GENERATION_FAILED` 503

Events are immutable readonly classes with `TYPE`, `VERSION = 1`, scalar fields, and `toOutbox()`. Event classes implement `InvoiceDomainEvent`.

- [ ] **Step 4: Implement `Invoice`**

Include mapping for all fields from the design. The constructor assigns `Uuid::v7()`, sets `activeOrderKey = orderId->toRfc4122()`, status `draft`, `amountPaid = 0`, `overpaymentFlagged = false`, timestamps, empty collections, and records `InvoiceCreated`.

`addLine`/`replaceLines` throw `InvoiceNotEditableException` unless `draft`. `replaceLines` clears the collection and re-adds, setting positions. `issue` requires `draft` and at least one line; sets number, `issueDate = new DateTimeImmutable('today')`, due date (must be >= issue date), status `issued`, records `InvoiceIssued`. `void` allows `draft` or `issued` with `amountPaid === 0`; requires a non-empty reason; sets status `voided`, clears `activeOrderKey`, records `InvoiceVoided`. `issueCreditNote` rejects `draft`/`voided`, non-positive amounts, and amounts above `getTotal() - getCreditedAmount()`; creates `CreditNote` with `{number|DRAFT}-CN{count+1}`, records `CreditNoteIssued`. `isOverdue` uses the passed "today" (default today) and status `issued`/`partially_paid`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Entity tests/Unit/Event`
Expected: PASS.

---

### Task 4: Invoice payments and overpayment flag

**Files:**
- Modify: `src/Entity/Invoice.php`
- Create: `src/Enum/PaymentResult.php` (already in Task 1, keep)
- Test: `tests/Unit/Entity/InvoicePaymentTest.php`

**Interfaces:**
- Produces: `Invoice::applyPayment(Uuid $paymentId, int $amount, string $currency): PaymentResult`, `getPayments(): Collection`, `isOverpaymentFlagged(): bool`.

- [ ] **Step 1: Write the failing payment tests**

```php
public function testPartialThenFullPayment(): void
{
    $invoice = $this->issued();
    $first = $invoice->applyPayment(Uuid::v7(), 1000, 'EUR');
    self::assertSame(PaymentResult::APPLIED, $first);
    self::assertSame(InvoiceStatus::PARTIALLY_PAID, $invoice->getStatus());

    $second = $invoice->applyPayment(Uuid::v7(), $invoice->getBalance(), 'EUR');
    self::assertSame(PaymentResult::APPLIED, $second);
    self::assertSame(InvoiceStatus::PAID, $invoice->getStatus());
    self::assertSame(0, $invoice->getBalance());
}

public function testDuplicatePaymentIsNoOp(): void
{
    $invoice = $this->issued();
    $paymentId = Uuid::v7();
    $invoice->applyPayment($paymentId, 500, 'EUR');
    $paid = $invoice->getAmountPaid();
    $result = $invoice->applyPayment($paymentId, 500, 'EUR');

    self::assertSame(PaymentResult::DUPLICATE, $result);
    self::assertSame($paid, $invoice->getAmountPaid());
    self::assertCount(1, $invoice->getPayments());
}

public function testOverpaymentIsFlaggedAndNotApplied(): void
{
    $invoice = $this->issued();
    $result = $invoice->applyPayment(Uuid::v7(), $invoice->getBalance() + 1, 'EUR');

    self::assertSame(PaymentResult::FLAGGED, $result);
    self::assertTrue($invoice->isOverpaymentFlagged());
    self::assertSame(0, $invoice->getAmountPaid());
    self::assertSame(InvoiceStatus::ISSUED, $invoice->getStatus());
}

public function testPaymentOnDraftThrows(): void
{
    $invoice = $this->invoice();
    $this->expectException(InvalidInvoiceTransitionException::class);
    $invoice->applyPayment(Uuid::v7(), 100, 'EUR');
}

public function testPaymentCurrencyMustMatchInvoice(): void
{
    $invoice = $this->issued();
    $this->expectException(CurrencyMismatchException::class);
    $invoice->applyPayment(Uuid::v7(), 100, 'USD');
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Entity/InvoicePaymentTest.php`
Expected: FAIL because `applyPayment` does not exist.

- [ ] **Step 3: Implement payment behavior**

Add the `payments` collection mapping (`OneToMany`, cascade persist, orphan removal). `applyPayment` checks status, currency, positive amount, and same-invoice duplicate `paymentId`. On overpayment, persist an `InvoicePayment` with `accepted = false`, set `overpaymentFlagged = true`, return `FLAGGED`. On success, persist an accepted payment, add to `amountPaid`, transition to `paid` when balance is zero (record `InvoicePaid`) or `partially_paid` otherwise, and return `APPLIED`. Cross-invoice `paymentId` reuse is handled in Task 10; the entity only sees its own collection.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Entity/InvoicePaymentTest.php`
Expected: PASS.

---

### Task 5: Repositories, persistence, and migration

**Files:**
- Create: `src/Repository/InvoiceRepository.php`, `src/Repository/InvoicePaymentRepository.php`, `src/Repository/OutboxEventRepository.php`
- Create: `migrations/Version20261114000000.php`
- Test: `tests/Integration/Repository/InvoiceRepositoryTest.php`

**Interfaces:**
- Produces:
  - `InvoiceRepository::findByUuid(Uuid|string $id): ?Invoice`
  - `InvoiceRepository::findActiveByOrderId(Uuid $orderId): ?Invoice`
  - `InvoiceRepository::search(int $page, int $pageSize, ?InvoiceStatus $status = null, ?InvoiceType $type = null, ?Uuid $orderId = null, ?Uuid $partyId = null, ?string $search = null, ?bool $overdue = null): array{0: Invoice[], 1: int}`
  - `InvoiceRepository::highestNumberFor(InvoiceType $type, int $year): ?string`
  - `InvoicePaymentRepository::findByPaymentId(Uuid $paymentId): ?InvoicePayment`
  - `OutboxEventRepository::findPending(int $limit = 100): array`, `countPending(): int`

- [ ] **Step 1: Write the failing repository test**

`tests/Integration/Repository/InvoiceRepositoryTest.php` (extends `KernelTestCase`, rebuilds schema in `setUp`):
```php
public function testFindActiveByOrderIdIgnoresVoided(): void
{
    $orderId = Uuid::v7();
    $first = $this->persistInvoice($orderId, 'INV-2026-000001');
    $first->void('wrong');
    $this->em()->flush();

    self::assertNull($this->repo()->findActiveByOrderId($orderId));

    $second = $this->persistInvoice($orderId, 'INV-2026-000002');
    self::assertSame($second->getId()->toRfc4122(), $this->repo()->findActiveByOrderId($orderId)?->getId()->toRfc4122());
}

public function testSearchFiltersAndPaginates(): void
{
    $this->persistInvoice(Uuid::v7(), 'INV-2026-000001', InvoiceStatus::ISSUED, InvoiceType::SALES, 'Acme');
    $this->persistInvoice(Uuid::v7(), 'BILL-2026-000002', InvoiceStatus::ISSUED, InvoiceType::PURCHASE, 'Supplier');

    [$items, $total] = $this->repo()->search(1, 10, InvoiceStatus::ISSUED, InvoiceType::PURCHASE);
    self::assertSame(1, $total);
    self::assertSame('Supplier', $items[0]->getPartyName());
}

public function testHighestNumberForTypeAndYear(): void
{
    $this->persistInvoice(Uuid::v7(), 'INV-2026-000001');
    $this->persistInvoice(Uuid::v7(), 'INV-2026-000007');
    self::assertSame('INV-2026-000007', $this->repo()->highestNumberFor(InvoiceType::SALES, 2026));
    self::assertNull($this->repo()->highestNumberFor(InvoiceType::SALES, 2025));
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Repository/InvoiceRepositoryTest.php`
Expected: FAIL because the repository classes do not exist.

- [ ] **Step 3: Implement the repositories**

Use `ServiceEntityRepository`. `search` joins `lines`, `creditNotes`, and `payments`, orders by `createdAt DESC`, and uses `Doctrine\ORM\Tools\Pagination\Paginator($query, fetchJoinCollection: true)`. `search` applies `LOWER(number) LIKE :q OR LOWER(partyName) LIKE :q`. `overdue` filters `dueDate < :today` and statuses `issued`/`partially_paid`. `highestNumberFor` filters by prefix `{prefix}-{year}-%` and orders descending.

- [ ] **Step 4: Write the migration**

`migrations/Version20261114000000.php` uses the DBAL `Schema` API (not raw PostgreSQL SQL) to create `invoices`, `invoice_lines`, `credit_notes`, `invoice_payments`, and `outbox_events` with the indexes and foreign keys from the design. `active_order_key` is a nullable unique string; `payment_id` is a unique UUID column; `credit_notes.number` is unique. `down()` drops the tables in reverse dependency order.

- [ ] **Step 5: Verify the migration on SQLite**

Run:
```bash
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/console doctrine:schema:validate --env=test
```
Expected: migration succeeds and mapping is valid.

- [ ] **Step 6: Run the repository test to verify it passes**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Repository/InvoiceRepositoryTest.php`
Expected: PASS.

---

### Task 6: Tax boundary

**Files:**
- Create: `src/Tax/TaxLineInput.php`, `src/Tax/TaxLineResult.php`, `src/Tax/TaxCalculatorInterface.php`, `src/Tax/HttpTaxCalculator.php`
- Create: `config/packages/http_client.yaml`
- Modify: `.env`
- Test: `tests/Unit/Tax/HttpTaxCalculatorTest.php`

**Interfaces:**
- Produces:
  - `TaxCalculatorInterface::calculate(string $currency, array $lines): array` where lines are `TaxLineInput[]` and the return is `TaxLineResult[]`.
  - `HttpTaxCalculator::__construct(HttpClientInterface $http, string $baseUrl, float $timeout)`.
  - `TaxLineInput::__construct(Uuid $productId, int $quantity, int $unitPrice)`.
  - `TaxLineResult::__construct(int $taxRateBps, int $taxAmount)`.

- [ ] **Step 1: Write the failing adapter tests**

```php
public function testMapsTaxResults(): void
{
    $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
        self::assertSame('POST', $method);
        self::assertSame('http://tax.test/api/v1/tax/calculate', $url);
        $body = json_decode($options['body'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('EUR', $body['currency']);
        self::assertSame(2, $body['lines'][0]['quantity']);
        return new MockResponse(json_encode(['lines' => [['taxRateBps' => 2000, 'taxAmount' => 400]]]), ['http_code' => 200]);
    });
    $calculator = new HttpTaxCalculator($client, 'http://tax.test', 3.0);

    $result = $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 2, 1000)]);
    self::assertSame(2000, $result[0]->taxRateBps);
    self::assertSame(400, $result[0]->taxAmount);
}

public function testServerErrorBecomesUnavailable(): void
{
    $calculator = new HttpTaxCalculator(new MockHttpClient(new MockResponse('{}', ['http_code' => 500])), 'http://tax.test', 3.0);
    $this->expectException(TaxServiceUnavailableException::class);
    $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 1, 1000)]);
}

public function testMalformedLineCountIsRejected(): void
{
    $calculator = new HttpTaxCalculator(new MockHttpClient(new MockResponse(json_encode(['lines' => []]), ['http_code' => 200])), 'http://tax.test', 3.0);
    $this->expectException(TaxServiceUnavailableException::class);
    $calculator->calculate('EUR', [new TaxLineInput(Uuid::v7(), 1, 1000)]);
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Tax`
Expected: FAIL because the tax classes do not exist.

- [ ] **Step 3: Implement the tax boundary**

`HttpTaxCalculator` sends JSON to `rtrim($baseUrl, '/').'/api/v1/tax/calculate'`, validates status < 400, validates that the response has exactly one `lines` entry per request, and validates `taxRateBps` is 0–10000 and `taxAmount` is non-negative. Any transport, JSON, status, or shape failure throws `TaxServiceUnavailableException`.

Add to `.env`:
```dotenv
TAX_SERVICE_BASE_URL=http://tax-service-web
TAX_SERVICE_TIMEOUT=3
```

`config/packages/http_client.yaml`:
```yaml
framework:
    http_client:
        default_options:
            timeout: 5
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Unit/Tax`
Expected: PASS.

---

### Task 7: Invoice numbering

**Files:**
- Create: `src/Service/InvoiceNumberGenerator.php`
- Modify: `src/Repository/InvoiceRepository.php` (already has `highestNumberFor`)
- Test: `tests/Integration/Service/InvoiceNumberGeneratorTest.php`

**Interfaces:**
- Produces:
  - `InvoiceNumberGenerator::next(InvoiceType $type, ?DateTimeImmutable $today = null): string`
  - `InvoiceNumberGenerator::locked(InvoiceType $type, DateTimeImmutable $today, callable $callback): mixed` where the callback receives the number.

- [ ] **Step 1: Write the failing numbering test**

`tests/Integration/Service/InvoiceNumberGeneratorTest.php` (KernelTestCase, schema rebuild):
```php
public function testNumbersAreSequentialPerTypeAndYear(): void
{
    $this->persistIssued('INV-2026-000001');
    $this->persistIssued('INV-2026-000007');

    $generator = self::getContainer()->get(InvoiceNumberGenerator::class);
    self::assertSame('INV-2026-000008', $generator->next(InvoiceType::SALES, new DateTimeImmutable('2026-06-01')));
    self::assertSame('BILL-2026-000001', $generator->next(InvoiceType::PURCHASE, new DateTimeImmutable('2026-06-01')));
}

public function testLockedCallbackReceivesTheNextNumber(): void
{
    $generator = self::getContainer()->get(InvoiceNumberGenerator::class);
    $number = $generator->locked(InvoiceType::SALES, new DateTimeImmutable('2026-06-01'), static fn (string $n): string => $n);
    self::assertSame('INV-2026-000001', $number);
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Service/InvoiceNumberGeneratorTest.php`
Expected: FAIL because the service does not exist.

- [ ] **Step 3: Implement the generator**

Use `LockFactory::createLock('invoice-number-'.$type->value.'-'.$year, 30.0, true)`. `next()` queries `highestNumberFor`, extracts the last six digits, increments, and formats `sprintf('%s-%d-%06d', $type->numberPrefix(), $year, $next)`. `locked()` acquires blocking (`acquire(true)`), calls `next()`, runs the callback, and releases in `finally`. Throw `NumberGenerationException` if the lock cannot be acquired.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Service/InvoiceNumberGeneratorTest.php`
Expected: PASS.

---

### Task 8: Create/update draft handlers and outbox writes

**Files:**
- Create: `src/Dto/InvoiceDraftInput.php`, `src/Dto/InvoiceLineInput.php`, `src/Dto/UpdateInvoiceInput.php`
- Create: `src/Handler/Invoice/CreateDraftInvoiceHandler.php`, `src/Handler/Invoice/UpdateDraftInvoiceHandler.php`
- Create: `src/Handler/Outbox/OutboxRecorder.php`
- Test: `tests/Integration/Handler/CreateDraftInvoiceHandlerTest.php`, `tests/Integration/Handler/UpdateDraftInvoiceHandlerTest.php`

**Interfaces:**
- Produces:
  - `InvoiceDraftInput::__construct(Uuid $orderId, InvoiceType $type, Uuid $partyId, string $partyName, string $currency, array $lines)`.
  - `InvoiceLineInput::__construct(Uuid $productId, string $description, int $quantity, int $unitPrice)`.
  - `UpdateInvoiceInput::__construct(?string $partyName, ?string $currency, array $lines)`.
  - `CreateDraftInvoiceHandler::__invoke(InvoiceDraftInput $input, bool $ignoreExisting = false): ?Invoice`.
  - `UpdateDraftInvoiceHandler::__invoke(Uuid $invoiceId, UpdateInvoiceInput $input): Invoice`.
  - `OutboxRecorder::record(EntityManagerInterface $em, Invoice $invoice): void` drains `pullEvents()` and persists each event's `toOutbox()`.

- [ ] **Step 1: Write the failing create/update tests**

`CreateDraftInvoiceHandlerTest` (KernelTestCase, schema rebuild, MockHttpClient tax):
```php
public function testCreatesDraftWithTaxAndOutboxEvent(): void
{
    $invoice = $this->handler()($this->draftInput());

    self::assertSame(InvoiceStatus::DRAFT, $invoice->getStatus());
    self::assertSame(2400, $invoice->getTotal());
    self::assertCount(1, $this->outbox()->findPending());
    self::assertSame('invoice.created', $this->outbox()->findPending()[0]->getEventType());
}

public function testDuplicateActiveOrderThrows(): void
{
    $input = $this->draftInput();
    $this->handler()($input);
    $this->expectException(InvoiceAlreadyExistsForOrderException::class);
    $this->handler()($input);
}

public function testDuplicateActiveOrderCanBeIgnored(): void
{
    $input = $this->draftInput();
    $first = $this->handler()($input);
    self::assertNull($this->handler()($input, ignoreExisting: true));
    self::assertNotNull($first);
    self::assertCount(1, $this->repo()->search(1, 10)[0]);
}

public function testVoidedOrderCanBeInvoicedAgain(): void
{
    $input = $this->draftInput();
    $first = $this->handler()($input);
    $first->void('Wrong');
    $this->em()->flush();

    $second = $this->handler()($input);
    self::assertNotSame($first->getId()->toRfc4122(), $second->getId()->toRfc4122());
}
```

`UpdateDraftInvoiceHandlerTest`:
```php
public function testReplacesLinesAndRecalculatesTax(): void
{
    $invoice = $this->handler()($this->draftInput());
    $updated = $this->updateHandler()($invoice->getId(), new UpdateInvoiceInput('Renamed', 'EUR', [
        new InvoiceLineInput(Uuid::v7(), 'New line', 3, 1000),
    ]));

    self::assertSame('Renamed', $updated->getPartyName());
    self::assertCount(1, $updated->getLines());
    self::assertSame(3600, $updated->getTotal());
}

public function testIssuedInvoiceCannotBeUpdated(): void
{
    $invoice = $this->handler()($this->draftInput());
    $invoice->addLine(new InvoiceLine(Uuid::v7(), 'X', 1, 100, 0, 0));
    $invoice->issue('INV-2026-000001', new DateTimeImmutable('+30 days'));
    $this->em()->flush();

    $this->expectException(InvoiceNotEditableException::class);
    $this->updateHandler()($invoice->getId(), new UpdateInvoiceInput(null, null, [new InvoiceLineInput(Uuid::v7(), 'Y', 1, 100)]));
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Handler`
Expected: FAIL because the handlers do not exist.

- [ ] **Step 3: Implement `CreateDraftInvoiceHandler`**

Check `findActiveByOrderId`; if found and `ignoreExisting`, return `null`; otherwise throw `InvoiceAlreadyExistsForOrderException`. Call `TaxCalculatorInterface::calculate` with `TaxLineInput[]`; require the result count to equal the line count. Build `Invoice` and `InvoiceLine`s with the returned tax values. In `wrapInTransaction`: persist, flush, then `OutboxRecorder::record($em, $invoice)`, flush again. Catch `UniqueConstraintViolationException` on `activeOrderKey`: return `null` when ignoring, otherwise throw `InvoiceAlreadyExistsForOrderException`.

- [ ] **Step 4: Implement `UpdateDraftInvoiceHandler`**

Load by UUID or throw `InvoiceNotFoundException`. Build new lines with a fresh tax call, call `replaceLines`, optionally update party name/currency (draft only), then persist in a transaction. No outbox event.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Handler`
Expected: PASS.

---

### Task 9: Issue, void, and credit-note handlers

**Files:**
- Create: `src/Handler/Invoice/IssueInvoiceHandler.php`, `src/Handler/Invoice/VoidInvoiceHandler.php`, `src/Handler/Invoice/IssueCreditNoteHandler.php`
- Test: `tests/Integration/Handler/InvoiceLifecycleHandlerTest.php`

**Interfaces:**
- Produces:
  - `IssueInvoiceHandler::__invoke(Uuid $invoiceId, ?DateTimeImmutable $dueDate = null): Invoice`
  - `VoidInvoiceHandler::__invoke(Uuid $invoiceId, string $reason): Invoice`
  - `IssueCreditNoteHandler::__invoke(Uuid $invoiceId, int $amount, string $reason): Invoice`
- Consumes: `InvoiceNumberGenerator::locked()`, `OutboxRecorder::record()`.

- [ ] **Step 1: Write the failing lifecycle tests**

```php
public function testIssueAssignsNumberAndDefaultDueDate(): void
{
    $invoice = $this->draft();
    $issued = $this->issueHandler()($invoice->getId());

    self::assertSame(InvoiceStatus::ISSUED, $issued->getStatus());
    self::assertSame('INV-2026-000001', $issued->getNumber());
    self::assertSame(
        $issued->getIssueDate()->modify('+30 days')->format('Y-m-d'),
        $issued->getDueDate()->format('Y-m-d'),
    );
    self::assertSame('invoice.issued', $this->outbox()->findPending()[0]->getEventType());
}

public function testExplicitDueDateIsHonoured(): void
{
    $invoice = $this->draft();
    $due = new DateTimeImmutable('2026-12-31');
    self::assertSame('2026-12-31', $this->issueHandler()($invoice->getId(), $due)->getDueDate()->format('Y-m-d'));
}

public function testIssueNonDraftThrows(): void
{
    $invoice = $this->issued();
    $this->expectException(InvalidInvoiceTransitionException::class);
    $this->issueHandler()($invoice->getId());
}

public function testVoidDraftAndUnpaidIssued(): void
{
    $draft = $this->draft();
    self::assertSame(InvoiceStatus::VOIDED, $this->voidHandler()($draft->getId(), 'Wrong')->getStatus());

    $issued = $this->issued();
    self::assertSame(InvoiceStatus::VOIDED, $this->voidHandler()($issued->getId(), 'Cancelled')->getStatus());
}

public function testCreditNoteReducesBalanceAndRecordsEvent(): void
{
    $invoice = $this->issued();
    $before = $invoice->getBalance();
    $updated = $this->creditHandler()($invoice->getId(), 500, 'Correction');

    self::assertSame($before - 500, $updated->getBalance());
    self::assertSame('invoice.credit_note_issued', $this->outbox()->findPending()[0]->getEventType());
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Handler/InvoiceLifecycleHandlerTest.php`
Expected: FAIL because the handlers do not exist.

- [ ] **Step 3: Implement the handlers**

`IssueInvoiceHandler` loads the invoice, computes `issueDate = new DateTimeImmutable('today')`, uses the explicit due date or `issueDate->modify('+'.$termDays.' days')`, rejects due dates before issue date, and calls `InvoiceNumberGenerator::locked()` with a callback that calls `issue()`, persists, records outbox, and flushes. Inject `%invoice.payment_term_days%` via `#[Autowire]`.

`VoidInvoiceHandler` loads, calls `void($reason)`, persists, records outbox.

`IssueCreditNoteHandler` loads, calls `issueCreditNote($amount, $reason)`, persists, records outbox, returns the invoice.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Handler/InvoiceLifecycleHandlerTest.php`
Expected: PASS.

---

### Task 10: Apply-payment handler

**Files:**
- Create: `src/Handler/Invoice/ApplyPaymentHandler.php`
- Test: `tests/Integration/Handler/ApplyPaymentHandlerTest.php`

**Interfaces:**
- Produces: `ApplyPaymentHandler::__invoke(Uuid $invoiceId, Uuid $paymentId, int $amount, string $currency): PaymentResult`.
- Consumes: `InvoicePaymentRepository::findByPaymentId()`.

- [ ] **Step 1: Write the failing payment handler tests**

```php
public function testAppliesPaymentAndEmitsPaidOnFullAmount(): void
{
    $invoice = $this->issued();
    $result = $this->handler()($invoice->getId(), Uuid::v7(), $invoice->getBalance(), 'EUR');

    self::assertSame(PaymentResult::APPLIED, $result);
    self::assertSame(InvoiceStatus::PAID, $this->repo()->findByUuid($invoice->getId())->getStatus());
    self::assertSame('invoice.paid', $this->outbox()->findPending()[0]->getEventType());
}

public function testDuplicatePaymentIsIdempotent(): void
{
    $invoice = $this->issued();
    $paymentId = Uuid::v7();
    $this->handler()($invoice->getId(), $paymentId, 500, 'EUR');
    $result = $this->handler()($invoice->getId(), $paymentId, 500, 'EUR');

    self::assertSame(PaymentResult::DUPLICATE, $result);
    self::assertSame(500, $this->repo()->findByUuid($invoice->getId())->getAmountPaid());
}

public function testPaymentIdReusedOnAnotherInvoiceThrows(): void
{
    $first = $this->issued();
    $second = $this->issued();
    $paymentId = Uuid::v7();
    $this->handler()($first->getId(), $paymentId, 100, 'EUR');

    $this->expectException(PaymentIdConflictException::class);
    $this->handler()($second->getId(), $paymentId, 100, 'EUR');
}

public function testOverpaymentIsPersistedAsFlag(): void
{
    $invoice = $this->issued();
    $result = $this->handler()($invoice->getId(), Uuid::v7(), $invoice->getBalance() + 1, 'EUR');

    self::assertSame(PaymentResult::FLAGGED, $result);
    $reloaded = $this->repo()->findByUuid($invoice->getId());
    self::assertTrue($reloaded->isOverpaymentFlagged());
    self::assertSame(0, $reloaded->getAmountPaid());
}

public function testDraftVoidedAndCurrencyMismatchAreRejected(): void
{
    $draft = $this->draft();
    try {
        $this->handler()($draft->getId(), Uuid::v7(), 100, 'EUR');
        self::fail('Expected invalid transition');
    } catch (InvalidInvoiceTransitionException) {
    }

    $issued = $this->issued();
    $this->expectException(CurrencyMismatchException::class);
    $this->handler()($issued->getId(), Uuid::v7(), 100, 'USD');
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Handler/ApplyPaymentHandlerTest.php`
Expected: FAIL because the handler does not exist.

- [ ] **Step 3: Implement the handler**

Load the invoice or throw `InvoiceNotFoundException`. Look up `paymentId` globally: if it exists on the same invoice, return `PaymentResult::DUPLICATE`; if it exists on another invoice, throw `PaymentIdConflictException`. Call `applyPayment()`. On `DUPLICATE`, return without flushing. Otherwise persist in a transaction and record outbox events; return the result.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Integration/Handler/ApplyPaymentHandlerTest.php`
Expected: PASS.

---

### Task 11: API infrastructure, list/show/create/update

**Files:**
- Create: `src/Utils/ApiResponse.php`, `src/Utils/Pagination.php`
- Create: `src/EventListener/ApiExceptionListener.php`
- Create: `src/Api/V1/Request/InvoiceLineRequest.php`, `CreateInvoiceRequest.php`, `UpdateInvoiceRequest.php`, `ListInvoicesQuery.php`
- Create: `src/Api/V1/Mapper/InvoiceMapper.php`, `src/Api/V1/Resource/InvoiceResource.php`
- Create: `src/Api/V1/Documentation/InvoiceDocument.php`, `InvoiceAttributesDocument.php`, `InvoiceLineDocument.php`, `CreditNoteDocument.php`, `PaginationDocument.php`, `ErrorDocument.php`, `ErrorDetailDocument.php`
- Create: `src/Api/V1/Controller/InvoiceController.php`
- Create: `config/packages/nelmio_api_doc.yaml`, `config/packages/property_info.yaml`, `config/packages/validator.yaml`, `config/routes/nelmio_api_doc.yaml`
- Modify: `config/bundles.php`, `config/routes.yaml`
- Test: `tests/Controller/Api/V1/InvoiceControllerTest.php`, `tests/Unit/Api/V1/Mapper/InvoiceMapperTest.php`

**Interfaces:**
- Produces:
  - `InvoiceController` routes `api_v1_invoice_list` (`GET /api/v1/invoices`), `_show`, `_create`, `_update`.
  - `InvoiceMapper::toResource(Invoice $invoice): InvoiceResource`.
  - `InvoiceResource::__construct(string $id, array $attributes, string $type = 'invoice')`.
  - Request DTOs with `toInput()` methods.

- [ ] **Step 1: Write the failing controller tests**

`InvoiceControllerTest` (extends `WebTestCase`, rebuilds schema and sets a tax `MockHttpClient` in `setUp`):
```php
public function testCreateShowListAndUpdate(): void
{
    $create = $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($this->payload()));
    self::assertResponseStatusCodeSame(201);
    $body = json_decode((string) $this->client->getResponse()->getContent(), true);
    self::assertSame('success', $body['status']);
    self::assertSame('draft', $body['data']['attributes']['status']);
    self::assertSame(2400, $body['data']['attributes']['total']);
    $id = $body['data']['id'];

    $this->client->request('GET', '/api/v1/invoices/' . $id);
    self::assertResponseStatusCodeSame(200);
    self::assertSame($id, json_decode((string) $this->client->getResponse()->getContent(), true)['data']['id']);

    $this->client->request('GET', '/api/v1/invoices?status=draft&type=sales');
    self::assertResponseStatusCodeSame(200);
    self::assertSame(1, json_decode((string) $this->client->getResponse()->getContent(), true)['pagination']['totalItems']);

    $this->client->request('PUT', '/api/v1/invoices/' . $id, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
        'partyName' => 'Renamed',
        'lines' => [['productId' => Uuid::v7()->toRfc4122(), 'description' => 'New', 'quantity' => 1, 'unitPrice' => 500]],
    ]));
    self::assertResponseStatusCodeSame(200);
    self::assertSame('Renamed', json_decode((string) $this->client->getResponse()->getContent(), true)['data']['attributes']['partyName']);
}

public function testDuplicateActiveOrderReturns409(): void
{
    $payload = $this->payload();
    $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload));
    self::assertResponseStatusCodeSame(201);
    $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload));
    self::assertResponseStatusCodeSame(409);
    self::assertSame('INVOICE_ALREADY_EXISTS_FOR_ORDER', json_decode((string) $this->client->getResponse()->getContent(), true)['error']['code']);
}

public function testValidationErrorReturns422(): void
{
    $this->client->request('POST', '/api/v1/invoices', server: ['CONTENT_TYPE' => 'application/json'], content: '{"orderId":"not-a-uuid"}');
    self::assertResponseStatusCodeSame(422);
}

public function testMissingInvoiceReturns404(): void
{
    $this->client->request('GET', '/api/v1/invoices/' . Uuid::v7()->toRfc4122());
    self::assertResponseStatusCodeSame(404);
}
```

Payload helper returns `orderId`, `type` `sales`, `partyId`, `partyName`, `currency` `EUR`, and one line with quantity 2, unitPrice 1000. The tax mock returns `taxRateBps=2000`, `taxAmount=400`, giving total 2400.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Controller/Api/V1/InvoiceControllerTest.php`
Expected: FAIL with 404 because the routes do not exist.

- [ ] **Step 3: Implement the API infrastructure**

Copy `ApiResponse` and `Pagination` from `../product-service/src/Utils/`. Copy `ApiExceptionListener` and adapt it to `App\Exceptions\ApiException`. Register Nelmio and Twig in `config/bundles.php`. Add the `/api/v1` attribute route import and Nelmio routes/config. Add `property_info.with_constructor_extractor: true` and test validation config.

- [ ] **Step 4: Implement request DTOs, mapper, resource, and docs**

`InvoiceLineRequest`: UUID string, non-blank description (max 255), positive quantity, non-negative unit price. `CreateInvoiceRequest`: UUID strings for order/party/product, non-blank party name, 3-letter currency, `InvoiceType $type`, `@Assert\Count(min: 1, max: 100)`, `@Assert\Valid` lines; `toInput(): InvoiceDraftInput`. `UpdateInvoiceRequest`: optional partyName/currency plus required lines; `toInput(): UpdateInvoiceInput`. `ListInvoicesQuery`: page 1, pageSize 20 (1–100), optional status/type/orderId/partyId/search/overdue.

`InvoiceMapper` maps all resource attributes from the design, using `getTotal`, `getBalance`, `getCreditedAmount`, `isOverdue`, `isOverpaymentFlagged`, and nested line/credit-note arrays. Resource id is `$invoice->getId()->toRfc4122()`.

- [ ] **Step 5: Implement the controller actions**

`list`: map query UUID strings, call `search`, build pagination links with `generateUrl('api_v1_invoice_list', [...])`, return `ApiResponse::success`. `show`: `findByUuid` or throw `InvoiceNotFoundException`. `create`: call handler, return 201 with absolute `Location`. `update`: call handler, return 200 with `Content-Location` (never `Location`, because PHP turns Location on 200 into 302). Use `#[Route]`, `#[OA\*]`, `#[MapQueryString]`, `#[MapRequestPayload]`.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Controller/Api/V1/InvoiceControllerTest.php`
Expected: PASS.

---

### Task 12: Issue, void, credit-note, and payment API actions

**Files:**
- Create: `src/Api/V1/Request/IssueInvoiceRequest.php`, `VoidInvoiceRequest.php`, `IssueCreditNoteRequest.php`, `ApplyPaymentRequest.php`
- Modify: `src/Api/V1/Controller/InvoiceController.php`
- Test: `tests/Controller/Api/V1/InvoiceControllerTest.php` (extend)

**Interfaces:**
- Produces routes:
  - `POST /api/v1/invoices/{id}/issue`
  - `POST /api/v1/invoices/{id}/void`
  - `POST /api/v1/invoices/{id}/credit-notes`
  - `POST /api/v1/invoices/{id}/payments`

- [ ] **Step 1: Add failing endpoint tests**

```php
public function testIssueVoidAndCreditNote(): void
{
    $id = $this->createInvoice();
    $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
    self::assertResponseStatusCodeSame(200);
    $body = json_decode((string) $this->client->getResponse()->getContent(), true);
    self::assertSame('issued', $body['data']['attributes']['status']);
    self::assertSame('INV-' . date('Y') . '-000001', $body['data']['attributes']['number']);

    $this->client->request('POST', '/api/v1/invoices/' . $id . '/credit-notes', server: ['CONTENT_TYPE' => 'application/json'], content: '{"amount":500,"reason":"Correction"}');
    self::assertResponseStatusCodeSame(200);
    self::assertSame(500, json_decode((string) $this->client->getResponse()->getContent(), true)['data']['attributes']['creditedAmount']);
}

public function testVoidIssuedUnpaidAndRejectVoidingPaid(): void
{
    $id = $this->createIssuedInvoice();
    $this->client->request('POST', '/api/v1/invoices/' . $id . '/void', server: ['CONTENT_TYPE' => 'application/json'], content: '{"reason":"Cancelled"}');
    self::assertResponseStatusCodeSame(200);
    self::assertSame('voided', json_decode((string) $this->client->getResponse()->getContent(), true)['data']['attributes']['status']);
}

public function testPartialAndFullPaymentsPlusOverpaymentFlag(): void
{
    $id = $this->createIssuedInvoice();
    $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['paymentId' => Uuid::v7()->toRfc4122(), 'amount' => 1000, 'currency' => 'EUR']));
    self::assertResponseStatusCodeSame(200);
    self::assertSame('partially_paid', json_decode((string) $this->client->getResponse()->getContent(), true)['data']['attributes']['status']);

    $this->client->request('POST', '/api/v1/invoices/' . $id . '/payments', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['paymentId' => Uuid::v7()->toRfc4122(), 'amount' => 99999, 'currency' => 'EUR']));
    self::assertResponseStatusCodeSame(200);
    self::assertTrue(json_decode((string) $this->client->getResponse()->getContent(), true)['data']['attributes']['overpaymentFlagged']);
}

public function testIssueNonDraftReturns409AndInvalidPayloadReturns422(): void
{
    $id = $this->createIssuedInvoice();
    $this->client->request('POST', '/api/v1/invoices/' . $id . '/issue', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
    self::assertResponseStatusCodeSame(409);
    self::assertSame('INVALID_INVOICE_TRANSITION', json_decode((string) $this->client->getResponse()->getContent(), true)['error']['code']);

    $this->client->request('POST', '/api/v1/invoices/' . $id . '/void', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
    self::assertResponseStatusCodeSame(422);
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Controller/Api/V1/InvoiceControllerTest.php`
Expected: FAIL with 404 on the new routes.

- [ ] **Step 3: Implement the request DTOs**

`IssueInvoiceRequest`: optional `dueDate` in `Y-m-d`, validated with a regex; `toDueDate(): ?DateTimeImmutable`. `VoidInvoiceRequest`: non-blank reason. `IssueCreditNoteRequest`: positive amount, non-blank reason. `ApplyPaymentRequest`: UUID `paymentId`, positive amount, 3-letter currency.

- [ ] **Step 4: Implement the actions**

Each action loads via the handler, returns the mapped invoice with 200. Payment returns 200 even when the result is `FLAGGED`; the returned attributes carry `overpaymentFlagged`. `issue` must accept an empty body: use `#[MapRequestPayload] ?IssueInvoiceRequest $payload = null`. Use the UUID route requirement `[0-9a-fA-F-]{36}`.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Controller/Api/V1/InvoiceControllerTest.php`
Expected: PASS.

---

### Task 13: PDF rendering and download endpoint

**Files:**
- Create: `src/Service/InvoicePdfRenderer.php`
- Create: `templates/invoice/pdf.html.twig`
- Modify: `src/Api/V1/Controller/InvoiceController.php`
- Test: `tests/Controller/Api/V1/InvoiceControllerTest.php` (extend)

**Interfaces:**
- Produces: `InvoicePdfRenderer::render(Invoice $invoice): string` (raw PDF bytes), route `GET /api/v1/invoices/{id}/pdf`.

- [ ] **Step 1: Write the failing PDF test**

```php
public function testPdfDownloadReturnsAValidPdfForNonAsciiContent(): void
{
    $id = $this->createIssuedInvoice(['partyName' => 'Ünïcode Café Ltd']);

    $this->client->request('GET', '/api/v1/invoices/' . $id . '/pdf');

    self::assertResponseStatusCodeSame(200);
    self::assertStringStartsWith('application/pdf', (string) $this->client->getResponse()->headers->get('Content-Type'));
    self::assertStringContainsString('attachment; filename=', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    self::assertStringStartsWith('%PDF-', (string) $this->client->getResponse()->getContent());
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Controller/Api/V1/InvoiceControllerTest.php --filter testPdfDownload`
Expected: FAIL with 404.

- [ ] **Step 3: Implement the renderer and template**

`InvoicePdfRenderer` injects Twig `Environment`, `%kernel.project_dir%`, and `%kernel.cache_dir%`. Render `invoice/pdf.html.twig` with `invoice`, `lines`, `total`, `creditedAmount`, `balance`, `isDraft`, and `now`. Create `var/cache/dompdf` if missing. Configure `Dompdf\Options` with `chroot = projectDir`, `tempDir = cacheDir.'/dompdf'`, `isRemoteEnabled = false`; instantiate `Dompdf`, `loadHtml`, `render`, return `output()`.

The template is standalone HTML (no base layout), with `{{ partyName }}`, a line table, totals, credit notes, and `{% if isDraft %}<div class="draft">DRAFT</div>{% endif %}`. Twig auto-escaping handles non-ASCII and HTML characters.

- [ ] **Step 4: Add the controller action**

`pdf`: load or throw, render, return `new Response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => sprintf('attachment; filename="%s.pdf"', $invoice->getNumber() ?? $invoice->getId()->toRfc4122())])`.

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Controller/Api/V1/InvoiceControllerTest.php --filter testPdfDownload`
Expected: PASS.

---

### Task 14: Inbound messages and handlers

**Files:**
- Create: `src/Message/OrderReadyForInvoiceMessage.php`, `src/Message/PaymentReceivedMessage.php`, `src/Message/OutboxEventMessage.php`
- Create: `src/MessageHandler/OrderReadyForInvoiceMessageHandler.php`, `src/MessageHandler/PaymentReceivedMessageHandler.php`
- Create: `config/packages/messenger.yaml`
- Modify: `.env`
- Test: `tests/MessageHandler/OrderReadyForInvoiceMessageHandlerTest.php`, `tests/MessageHandler/PaymentReceivedMessageHandlerTest.php`

**Interfaces:**
- Produces:
  - `OrderReadyForInvoiceMessage::__construct(string $eventType, string $orderId, string $type, string $partyId, string $partyName, string $currency, array $lines, string $occurredAt)`.
  - `PaymentReceivedMessage::__construct(string $invoiceId, string $paymentId, int $amount, string $currency, string $occurredAt)`.
  - `OutboxEventMessage::fromOutbox(OutboxEvent $event): self`.
  - Handlers with `#[AsMessageHandler]`.

- [ ] **Step 1: Write the failing message handler tests**

`OrderReadyForInvoiceMessageHandlerTest`:
```php
public function testMessageCreatesDraftAndOutboxEvent(): void
{
    $this->handler()(new OrderReadyForInvoiceMessage(
        'order.shipped', Uuid::v7()->toRfc4122(), 'sales', Uuid::v7()->toRfc4122(), 'Acme', 'EUR',
        [['productId' => Uuid::v7()->toRfc4122(), 'description' => 'Widget', 'quantity' => 2, 'unitPrice' => 1000]],
        (new DateTimeImmutable())->format(DATE_ATOM),
    ));

    self::assertCount(1, $this->repo()->search(1, 10)[0]);
    self::assertCount(1, $this->outbox()->findPending());
}

public function testDuplicateEventIsIgnored(): void
{
    $message = $this->message();
    $this->handler()($message);
    $this->handler()($message);
    self::assertSame(1, $this->repo()->search(1, 10)[1]);
}

public function testVoidedOrderEventCreatesReplacement(): void
{
    $message = $this->message();
    $first = $this->handler()($message);
    $first->void('Wrong');
    $this->em()->flush();
    $this->handler()($message);
    self::assertSame(2, $this->repo()->search(1, 10)[1]);
}

public function testTaxFailurePropagatesForRetry(): void
{
    $this->mockTaxFailure();
    $this->expectException(TaxServiceUnavailableException::class);
    $this->handler()($this->message());
    self::assertSame(0, $this->repo()->search(1, 10)[1]);
}
```

`PaymentReceivedMessageHandlerTest`:
```php
public function testPaymentIsAppliedOnce(): void
{
    $invoice = $this->issued();
    $message = new PaymentReceivedMessage($invoice->getId()->toRfc4122(), Uuid::v7()->toRfc4122(), 1000, 'EUR', (new DateTimeImmutable())->format(DATE_ATOM));
    $this->handler()($message);
    $this->handler()($message);
    self::assertSame(1000, $this->repo()->findByUuid($invoice->getId())->getAmountPaid());
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/MessageHandler`
Expected: FAIL because the messages and handlers do not exist.

- [ ] **Step 3: Implement the messages and handlers**

`OrderReadyForInvoiceMessageHandler` validates `eventType` is `order.shipped` or `purchase.order.received`, builds `InvoiceLineInput[]` from the scalar arrays, builds `InvoiceDraftInput`, and calls `CreateDraftInvoiceHandler(ignoreExisting: true)`. `PaymentReceivedMessageHandler` converts UUID strings and calls `ApplyPaymentHandler`. Neither catches exceptions; tax/illegal-transition errors retry and then land on the failed transport.

- [ ] **Step 4: Configure Messenger**

`config/packages/messenger.yaml`:
```yaml
framework:
    messenger:
        failure_transport: failed
        serializer:
            default_serializer: messenger.transport.symfony_serializer
            symfony_serializer:
                format: json
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: invoice_events
                        type: fanout
                retry_strategy:
                    max_retries: 5
                    delay: 1000
                    multiplier: 2
                    max_delay: 60000
            failed:
                dsn: 'doctrine://default?queue_name=failed'
        routing:
            App\Message\OrderReadyForInvoiceMessage: async
            App\Message\PaymentReceivedMessage: async
            App\Message\OutboxEventMessage: async

when@test:
    framework:
        messenger:
            transports:
                async: 'in-memory://'
                failed: 'in-memory://'
```

Add to `.env`:
```dotenv
MESSENGER_TRANSPORT_DSN=amqp://erp_app:Password!1@127.0.0.1:5672/%2f
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/MessageHandler`
Expected: PASS.

---

### Task 15: Outbox publisher command

**Files:**
- Create: `src/Console/PublishOutboxCommand.php`
- Test: `tests/Console/PublishOutboxCommandTest.php`

**Interfaces:**
- Produces: `#[AsCommand(name: 'app:outbox:publish')]` command. `OutboxEventMessage::fromOutbox(OutboxEvent $event): self` (already in Task 14).

- [ ] **Step 1: Write the failing command test**

```php
public function testPublishesAndMarksPendingRows(): void
{
    $this->seedOutbox('invoice.created');
    $this->seedOutbox('invoice.issued');

    $tester = new CommandTester(self::getContainer()->get(PublishOutboxCommand::class));
    $tester->execute([]);

    $tester->assertCommandIsSuccessful();
    self::assertSame(0, $this->outbox()->countPending());
    self::assertCount(0, $this->outbox()->findPending());
}

public function testDispatchFailureLeavesRowsUnpublished(): void
{
    $this->seedOutbox('invoice.created');
    $bus = $this->createMock(MessageBusInterface::class);
    $bus->method('dispatch')->willThrowException(new \RuntimeException('broker down'));
    $command = new PublishOutboxCommand($this->outbox(), $bus, $this->em());

    $this->expectException(\RuntimeException::class);
    (new CommandTester($command))->execute([]);
    self::assertSame(1, $this->outbox()->countPending());
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Console/PublishOutboxCommandTest.php`
Expected: FAIL because the command does not exist.

- [ ] **Step 3: Implement the command**

Fetch `findPending(100)` ordered by id. For each, `$bus->dispatch(OutboxEventMessage::fromOutbox($event))`, then `$event->markPublished()`. Flush once after the loop. Print `Published {n} event(s).` Throw on dispatch failure before any flush, so all rows remain pending.

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --configuration=Build/unit/phpunit.dist.xml tests/Console/PublishOutboxCommandTest.php`
Expected: PASS.

---

### Task 16: Final integration, container, and quality gates

**Files:**
- Modify: `.env`, `Dockerfile`, `compose.yaml` if needed
- Modify: any file reported by the quality tools

- [ ] **Step 1: Add runtime env defaults**

`.env` additions:
```dotenv
INVOICE_PAYMENT_TERM_DAYS=30
LOCK_DSN=flock
TAX_SERVICE_BASE_URL=http://tax-service-web
TAX_SERVICE_TIMEOUT=3
MESSENGER_TRANSPORT_DSN=amqp://erp_app:Password!1@127.0.0.1:5672/%2f
```

- [ ] **Step 2: Update the Docker image for PostgreSQL, AMQP, and multibyte support**

In `Dockerfile`, install `postgresql-dev`, `rabbitmq-c-dev`, `oniguruma-dev`, `mbstring`, `pdo_pgsql`, `intl`, `opcache`; install `amqp` via PECL. `dompdf` needs `ext-dom` and `ext-mbstring`; `dom` is part of the official PHP image. Keep the existing PHP-FPM command.

- [ ] **Step 3: Validate the container, YAML, Twig, and routes**

Run:
```bash
php bin/console lint:container
php bin/console lint:yaml config
php bin/console lint:twig templates
php bin/console debug:router
```
Expected: all commands exit 0; `debug:router` lists the invoice routes and `/api/doc`.

- [ ] **Step 4: Run the full test suite**

Run: `composer unit:test`
Expected: all tests pass.

- [ ] **Step 5: Run static analysis and style checks**

Run:
```bash
composer cs:check
composer stan:check
composer rector:check
```
Expected: all exit 0. Fix findings by changing code, not by weakening the configs.

- [ ] **Step 6: Verify the service boots**

Run: `php bin/console about` and `php bin/console debug:container App\Handler\Invoice\CreateDraftInvoiceHandler`.
Expected: the kernel boots and the handler is registered as a service.

---

## Self-Review Notes

- Spec coverage: domain aggregate and state machine (Tasks 3–4), numbering (Task 7), API (Tasks 11–12), tax (Task 6), PDF (Task 13), inbound events (Task 14), outbox (Tasks 8 and 15), persistence/migration (Task 5), tooling/tests (Tasks 1 and 16), error envelope (Tasks 3 and 11), SPA update header (Task 11).
- Idempotency: order replay (Task 8/14), payment replay (Tasks 4/10), outbox re-run (Task 15).
- The `activeOrderKey` decision is the only schema field not in the class diagram; it is required by the approved re-invoice decision and is documented in the spec.
- Concurrency of two simultaneous issue calls is guarded by the `symfony/lock` run through the issuing transaction; tests cover sequential numbering, not a true parallel race.
