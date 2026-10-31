# Inventory Movements and Product Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reshape the inventory service around one decimal stock row per product, add a record-only movement audit trail, cache inventory reads, and consume the product service's `ProductUpdated` message.

**Architecture:** Doctrine entities (`Inventory`, `InventoryMovement`) and one migration hold the new schema. A thin controller uses `#[MapRequestPayload]` DTOs and a tag-aware `cache.inventory` pool. A Messenger AMQP transport declares a dedicated `inventory` queue on the existing `messages` fanout exchange, and an `inventory-worker` compose service runs the consumer.

**Tech Stack:** Symfony 8.1, PHP >= 8.4, Doctrine ORM 3.7, MySQL 8 (dev/prod), SQLite (tests), Redis via Predis, RabbitMQ via AMQP, PHPUnit 13, php-cs-fixer.

**Spec:** `docs/superpowers/specs/2026-10-29-inventory-movements-design.md`

## Global Constraints

- Symfony 8.1, PHP >= 8.4, Doctrine ORM 3.7; MySQL 8 in dev/prod, SQLite in tests.
- Product IDs are integers. `Inventory.productId` is unique and immutable after creation.
- All stock values are `DECIMAL(12,3)` stored as strings (`"100.000"`). No `ext-bcmath`.
- Movements are record-only as a source of balance changes; explicit movement creation never changes `quantityOnHand` or `quantityReserved` (amendment A below).
- Movement `productId` is derived from its parent inventory, never from the request.
- Deleting an inventory soft-deletes it and keeps its movements (amendment A below).
- Inventory JSON is entity-shaped with `#[Groups(['inventory:read'])]`; no embedded relationships; lists are bare arrays.
- Tests must not require Redis or RabbitMQ; the test transport is `in-memory://`.
- `App\Message\ProductUpdated` must have the same FQCN and public properties as the product service's class (`productId`, `sku`, `name`, `sellingPrice`, `unit`).
- The product service publishes to the `messages` fanout exchange; inventory declares a separate `inventory` queue.
- The runtime image must load `ext-amqp`; add `rabbitmq-c-dev` + `pecl install amqp` to the Dockerfile.

## Review Focus

The five failure modes most likely to bite a user, and the task that pins each with a test:

1. **Duplicate `ProductUpdated` delivery** must not create a second inventory row or reset an existing row — Task 7 idempotency test.
2. **Stale or concurrent updates** must return `409`, not silently lose the write — Task 5 stale-version and `OptimisticLockException` handling.
3. **Decimal precision at 3 places** (`0.001`, `999999999.999`, negative/equal thresholds) must not drift — Task 1 unit tests and Task 5 create/compare tests.
4. **Duplicate or changed `productId`** must return `409` on create and `422` on update — Task 5 tests.
5. **Cache staleness after writes** must be fixed by tag invalidation for create/update/delete/handler — Task 5 cache test and Task 7 handler cache test.

## File Map

**Create**

- `src/Util/Decimal.php` — string-safe thousandths arithmetic and formatting.
- `src/Enum/InventoryMovementType.php` — `purchase_receipt`, `sale`, `return`, `adjustment`.
- `src/Entity/InventoryMovement.php` — audit row belonging to one inventory.
- `src/Dto/CreateInventoryInput.php` — create payload.
- `src/Dto/UpdateInventoryInput.php` — update payload (all fields optional).
- `src/Dto/CreateInventoryMovementInput.php` — movement payload.
- `src/Message/ProductUpdated.php` — mirror of the product service message.
- `src/MessageHandler/ProductUpdatedHandler.php` — ensure an inventory row exists.
- `migrations/Version*.php` — generated schema migration.
- `tests/Unit/Util/DecimalTest.php`
- `tests/Integration/Repository/InventoryRepositoryTest.php`
- `tests/Integration/Api/InventoryMovementControllerTest.php`
- `tests/Integration/MessageHandler/ProductUpdatedHandlerTest.php`

**Modify**

- `src/Entity/Inventory.php` — replace product metadata with stock fields, groups, version, movements.
- `src/Repository/InventoryRepository.php` — `findByProductId`, `findLowStock`, `findOutOfStock`.
- `src/Controller/Api/InventoryController.php` — new CRUD, cache, movement endpoints.
- `config/packages/cache.yaml` — `cache.inventory` Redis pool + test array pool.
- `config/packages/messenger.yaml` — `inventory` transport and routing.
- `tests/Unit/Entity/InventoryTest.php` — decimal/entity/relationship tests.
- `tests/Integration/Api/InventoryControllerTest.php` — new API and cache tests.
- `tests/Application/Api/InventoryControllerApplicationTest.php` — lifecycle including movements.
- `Dockerfile` — install `ext-amqp`.
- `.github/workflows/test.yml` — install `ext-amqp` in CI.
- `docker-compose.inventory.yml` (repo root) — fix Redis URL, add RabbitMQ DSN, add worker.
- `composer.json`, `composer.lock`, `symfony.lock` — new packages.

**Leave alone**

- `src/EventListener/ExceptionListener.php` already maps `HttpExceptionInterface` to JSON; `MapRequestPayload` and `createNotFoundException()` flow through it.
- `docker/supervisord.conf` stays unused; the worker is a separate compose service.
- `tests/Controller/InventoryControllerTest.php` is outside every phpunit testsuite; do not extend it.

---

### Task 0: Record the Approved Design and Plan

**Files:**
- Modify: `docs/superpowers/specs/2026-10-29-inventory-movements-design.md`
- Create: `docs/superpowers/plans/2026-10-29-inventory-movements.md`

- [ ] **Step 1: Commit the documentation**

```bash
git add docs/superpowers/specs/2026-10-29-inventory-movements-design.md docs/superpowers/plans/2026-10-29-inventory-movements.md
git commit -m "docs: approve inventory movements design and plan"
```

---

### Task 1: Decimal Helper

**Files:**
- Create: `src/Util/Decimal.php`
- Test: `tests/Unit/Util/DecimalTest.php`

**Interfaces:**
- Produces:
  - `App\Util\Decimal::format(string|int|float $value): string`
  - `App\Util\Decimal::subtract(string $minuend, string $subtrahend): string`
  - `App\Util\Decimal::compare(string $left, string $right): int`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace App\Tests\Unit\Util;

use App\Util\Decimal;
use PHPUnit\Framework\TestCase;

final class DecimalTest extends TestCase
{
    public function testFormatRoundsToThreePlaces(): void
    {
        self::assertSame('12.346', Decimal::format(12.3456));
        self::assertSame('100.500', Decimal::format('100.5'));
        self::assertSame('0.000', Decimal::format(0));
    }

    public function testSubtractKeepsThreePlaces(): void
    {
        self::assertSame('79.500', Decimal::subtract('100.000', '20.500'));
        self::assertSame('0.001', Decimal::subtract('100.000', '99.999'));
    }

    public function testSubtractCanGoNegative(): void
    {
        self::assertSame('-5.000', Decimal::subtract('5.000', '10.000'));
    }

    public function testCompareNormalizesScale(): void
    {
        self::assertSame(0, Decimal::compare('10.000', '10.0'));
        self::assertSame(-1, Decimal::compare('9.999', '10.000'));
        self::assertSame(1, Decimal::compare('10.001', '10.000'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/phpunit --testsuite Unit --filter DecimalTest`
Expected: FAIL with "Class \"App\\Util\\Decimal\" not found".

- [ ] **Step 3: Implement `Decimal`**

Parse to integer thousandths: strip the sign, split on `.`, left-pad the fraction to exactly 3 digits, and compute `integer * 1000 + fraction`; apply the sign. Format back with `sprintf('%s%d.%03d', $sign, intdiv(abs($milli), 1000), abs($milli) % 1000)`. `format()` uses `number_format((float) $value, 3, '.', '')` so incoming JSON numbers and strings round consistently; `subtract()` and `compare()` use the exact thousandths parser.

- [ ] **Step 4: Run it to verify it passes**

Run: `php bin/phpunit --testsuite Unit --filter DecimalTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Util/Decimal.php tests/Unit/Util/DecimalTest.php
git commit -m "feat(inventory): add decimal thousandths helper"
```

---

### Task 2: Inventory and Movement Entities

**Files:**
- Modify: `src/Entity/Inventory.php` (replace the whole class)
- Create: `src/Entity/InventoryMovement.php`
- Create: `src/Enum/InventoryMovementType.php`
- Test: `tests/Unit/Entity/InventoryTest.php` (replace the whole file)

**Interfaces:**
- Produces:
  - `Inventory::getId(): ?int`, `getProductId(): ?int`, `setProductId(int): static`
  - `Inventory::getQuantityOnHand(): ?string`, `setQuantityOnHand(string): static`
  - `Inventory::getQuantityReserved(): ?string`, `setQuantityReserved(string): static`
  - `Inventory::getReorderLevel(): ?string`, `setReorderLevel(string): static`
  - `Inventory::getCreatedAt(): ?\DateTimeImmutable`, `setCreatedAt(\DateTimeImmutable): static`
  - `Inventory::getUpdatedAt(): ?\DateTimeImmutable`, `setUpdatedAt(\DateTimeImmutable): static`
  - `Inventory::getVersion(): int`
  - `Inventory::getAvailableQuantity(): string` (`quantityOnHand - quantityReserved`)
  - `Inventory::isLowStock(): bool` (`available <= reorderLevel`)
  - `Inventory::getMovements(): Collection`, `addMovement(InventoryMovement): static`, `removeMovement(InventoryMovement): static`
  - `InventoryMovement::getId(): ?int`, `getInventory(): ?Inventory`, `setInventory(?Inventory): static` (also copies `productId` from the parent), `getProductId(): ?int`, `getType(): ?InventoryMovementType`, `setType(InventoryMovementType): static`, `getQuantity(): ?string`, `setQuantity(string): static`, `getReferenceType(): ?string`, `setReferenceType(?string): static`, `getReferenceId(): ?string`, `setReferenceId(?string): static`, `getReason(): ?string`, `setReason(?string): static`, `getCreatedAt(): ?\DateTimeImmutable`, `setCreatedAt(\DateTimeImmutable): static`
  - `InventoryMovementType::PurchaseReceipt = 'purchase_receipt'`, `Sale = 'sale'`, `Return = 'return'`, `Adjustment = 'adjustment'`
- Consumes: `Decimal::subtract()` and `Decimal::compare()` from Task 1.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use App\Enum\InventoryMovementType;
use PHPUnit\Framework\TestCase;

final class InventoryTest extends TestCase
{
    public function testAvailableQuantityIsTheDecimalDifference(): void
    {
        $inventory = (new Inventory())
            ->setQuantityOnHand('100.000')
            ->setQuantityReserved('20.500');

        self::assertSame('79.500', $inventory->getAvailableQuantity());
    }

    public function testAvailableQuantityCanBeNegative(): void
    {
        $inventory = (new Inventory())
            ->setQuantityOnHand('5.000')
            ->setQuantityReserved('10.000');

        self::assertSame('-5.000', $inventory->getAvailableQuantity());
    }

    public function testLowStockUsesLessThanOrEqual(): void
    {
        $inventory = (new Inventory())
            ->setQuantityOnHand('100.000')
            ->setQuantityReserved('90.000')
            ->setReorderLevel('10.000');

        self::assertTrue($inventory->isLowStock());
    }

    public function testNotLowStockAboveReorderLevel(): void
    {
        $inventory = (new Inventory())
            ->setQuantityOnHand('100.000')
            ->setQuantityReserved('10.000')
            ->setReorderLevel('20.000');

        self::assertFalse($inventory->isLowStock());
    }

    public function testMovementCollectionKeepsBothSidesInSync(): void
    {
        $inventory = (new Inventory())->setProductId(42);
        $movement = (new InventoryMovement())
            ->setType(InventoryMovementType::Sale)
            ->setQuantity('5.000')
            ->setCreatedAt(new \DateTimeImmutable());

        $inventory->addMovement($movement);

        self::assertTrue($inventory->getMovements()->contains($movement));
        self::assertSame($inventory, $movement->getInventory());
        self::assertSame(42, $movement->getProductId());

        $inventory->removeMovement($movement);

        self::assertFalse($inventory->getMovements()->contains($movement));
        self::assertNull($movement->getInventory());
    }

    public function testVersionStartsAtOne(): void
    {
        self::assertSame(1, (new Inventory())->getVersion());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/phpunit --testsuite Unit --filter InventoryTest`
Expected: FAIL — the current entity has `setQuantity`/`setReservedQuantity` and no `InventoryMovement`.

- [ ] **Step 3: Implement the entities and enum**

`Inventory` mapping: integer generated `id`; `#[ORM\Column(unique: true)] ?int $productId = null`; three `#[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)] ?string` fields defaulting to `'0.000'`; non-null `createdAt`/`updatedAt`; `#[ORM\Version] #[ORM\Column] int $version = 1`; `#[ORM\OneToMany(mappedBy: 'inventory', targetEntity: InventoryMovement::class, cascade: ['remove'], orphanRemoval: true)] Collection $movements`, initialized in the constructor. Add `#[Groups(['inventory:read'])]` to every scalar property, `getAvailableQuantity()`, and `isLowStock()`; do **not** add it to `getMovements()`. Derived methods call `Decimal`.

`InventoryMovement` mapping: integer generated `id`; `#[ORM\ManyToOne(inversedBy: 'movements')] #[ORM\JoinColumn(nullable: false)] ?Inventory $inventory`; `#[ORM\Column] ?int $productId`; `#[ORM\Column(length: 32, enumType: InventoryMovementType::class)] ?InventoryMovementType $type`; `#[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)] ?string $quantity`; nullable `referenceType` (32), `referenceId` (255), `reason` (TEXT); non-null `createdAt`. Add `#[Groups(['inventory:read'])]` to all scalar properties; do **not** add it to `getInventory()`. Implement `setInventory()` so it sets `$this->productId = $inventory?->getProductId()`; `Inventory::addMovement()` calls `$movement->setInventory($this)`, and `removeMovement()` nulls the inverse only when it belongs to this inventory.

`InventoryMovementType` is a `string`-backed enum with the four cases above.

- [ ] **Step 4: Run it to verify it passes**

Run: `php bin/phpunit --testsuite Unit`
Expected: PASS (DecimalTest + InventoryTest).

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Inventory.php src/Entity/InventoryMovement.php src/Enum/InventoryMovementType.php tests/Unit/Entity/InventoryTest.php
git commit -m "feat(inventory): model decimal stock levels and movements"
```

---

### Task 3: Schema Migration and Test Database

**Files:**
- Create: `migrations/Version<timestamp>.php` (generated by `make:migration`)

**Interfaces:**
- Consumes: the entity mapping from Task 2.
- Produces: MySQL schema with `inventory` stock columns and `inventory_movement`; a fresh SQLite test schema for Tasks 4–7.

- [ ] **Step 1: Create the dev database and apply the existing migration**

Run:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

Expected: the existing `Version20261018185953` runs. Then confirm the table is empty:

```bash
php bin/console doctrine:query:sql "SELECT COUNT(*) AS rows_count FROM inventory"
```

Expected: `rows_count` is `0`. If it is not, stop and ask the user before dropping rows; this plan assumes fixtures only.

- [ ] **Step 2: Generate the migration**

Run: `php bin/console make:migration --no-interaction`
Expected: a new `migrations/Version<timestamp>.php`.

- [ ] **Step 3: Inspect the generated migration**

It must:

- create `inventory_movement` with a non-null `inventory_id` FK to `inventory`, an index on `inventory_id`, and the movement columns;
- add `product_id`, `quantity_on_hand`, `quantity_reserved`, `reorder_level`, and `version` to `inventory`;
- add a unique index on `inventory.product_id`;
- drop `sku`, `name`, `description`, `quantity`, `reserved_quantity`, `min_stock_level`, and `location`;
- make `updated_at` non-null.

If it contains unrelated tables or columns, fix the entity mapping and regenerate. Do not edit the old migration.

- [ ] **Step 4: Apply and validate the migration**

Run:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

Expected: both commands exit 0; schema validation reports the mapping and database are in sync.

- [ ] **Step 5: Recreate the SQLite test schema**

Run:

```bash
rm -f var/data_test.db
php bin/console doctrine:schema:create --env=test
php bin/console doctrine:schema:validate --env=test
```

Expected: the test database is recreated with the new tables; both validations pass.

- [ ] **Step 6: Run the unit tests**

Run: `php bin/phpunit --testsuite Unit`
Expected: PASS. No integration test is run yet.

- [ ] **Step 7: Commit**

```bash
git add migrations/Version*.php
git commit -m "feat(inventory): migrate to decimal stock and movements"
```

---

### Task 4: Repository Queries

**Files:**
- Modify: `src/Repository/InventoryRepository.php`
- Test: `tests/Integration/Repository/InventoryRepositoryTest.php`

**Interfaces:**
- Produces:
  - `InventoryRepository::findByProductId(int $productId): ?Inventory`
  - `InventoryRepository::findLowStock(): array<Inventory>` ordered by available quantity ascending
  - `InventoryRepository::findOutOfStock(): array<Inventory>` ordered by available quantity ascending

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Inventory;
use App\Repository\InventoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InventoryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private InventoryRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->repository = $this->em->getRepository(Inventory::class);
        $this->em->getConnection()->executeStatement('DELETE FROM inventory_movement');
        $this->em->getConnection()->executeStatement('DELETE FROM inventory');
        $this->em->clear();
    }

    public function testFindByProductId(): void
    {
        $this->seed(2, '100.000', '10.000', '20.000');

        self::assertNotNull($this->repository->findByProductId(2));
        self::assertNull($this->repository->findByProductId(99));
    }

    public function testFindLowStockIncludesOutOfStockOrderedByAvailable(): void
    {
        $this->seed(1, '10.000', '2.000', '10.000'); // available 8
        $this->seed(2, '100.000', '10.000', '20.000'); // available 90
        $this->seed(3, '5.000', '5.000', '0.000'); // available 0

        self::assertSame([3, 1], array_map(
            static fn (Inventory $i): ?int => $i->getProductId(),
            $this->repository->findLowStock()
        ));
    }

    public function testFindOutOfStock(): void
    {
        $this->seed(1, '10.000', '2.000', '10.000');
        $this->seed(3, '5.000', '5.000', '0.000');

        self::assertSame([3], array_map(
            static fn (Inventory $i): ?int => $i->getProductId(),
            $this->repository->findOutOfStock()
        ));
    }

    private function seed(int $productId, string $onHand, string $reserved, string $reorder): void
    {
        $now = new \DateTimeImmutable();
        $inventory = (new Inventory())
            ->setProductId($productId)
            ->setQuantityOnHand($onHand)
            ->setQuantityReserved($reserved)
            ->setReorderLevel($reorder)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->em->persist($inventory);
        $this->em->flush();
        $this->em->clear();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/phpunit --testsuite Integration --filter InventoryRepositoryTest`
Expected: FAIL — `findByProductId` is missing and the existing low-stock query references dropped columns.

- [ ] **Step 3: Implement the repository methods**

Replace `findBySku()` with `findByProductId()` using `findOneBy(['productId' => $productId])`. Rewrite the DQL:

- low stock: `i.quantityOnHand - i.quantityReserved <= i.reorderLevel`, ordered by that expression ascending;
- out of stock: `i.quantityOnHand - i.quantityReserved <= 0`, ordered by that expression ascending.

- [ ] **Step 4: Run it to verify it passes**

Run: `php bin/phpunit --testsuite Integration --filter InventoryRepositoryTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Repository/InventoryRepository.php tests/Integration/Repository/InventoryRepositoryTest.php
git commit -m "feat(inventory): query stock by product and threshold"
```

---

### Task 5: Inventory API and Caching

**Files:**
- Create: `src/Dto/CreateInventoryInput.php`
- Create: `src/Dto/UpdateInventoryInput.php`
- Modify: `src/Controller/Api/InventoryController.php` (replace the class)
- Modify: `config/packages/cache.yaml`
- Modify: `tests/Integration/Api/InventoryControllerTest.php` (replace the file)
- Modify: `tests/Application/Api/InventoryControllerApplicationTest.php` (rewrite for new fields; movements are added in Task 6)
- Modify: `composer.json`, `composer.lock`, `symfony.lock`

**Interfaces:**
- Consumes: `Decimal`, entities, and repository from Tasks 1, 2, and 4.
- Produces:
  - `CreateInventoryInput::__construct(?int $productId = null, float|string|null $quantityOnHand = null, float|string|null $quantityReserved = null, float|string|null $reorderLevel = null)`
  - `UpdateInventoryInput::__construct(?int $productId = null, float|string|null $quantityOnHand = null, float|string|null $quantityReserved = null, float|string|null $reorderLevel = null, ?int $version = null)`
  - Routes: `GET /api/inventory`, `GET /api/inventory/{id<\d+>}`, `GET /api/inventory/product/{productId<\d+>}`, `POST /api/inventory`, `PUT|PATCH /api/inventory/{id<\d+>}`, `DELETE /api/inventory/{id<\d+>}`, `GET /api/inventory/low-stock`, `GET /api/inventory/out-of-stock`
  - Cache keys `inventory_page_{page}_limit_{limit}`, `inventory_id_{id}`, `inventory_product_{productId}`, `inventory_low_stock`, `inventory_out_of_stock`; tag `inventory`; TTL 300.

- [ ] **Step 1: Install the cache dependencies**

Run: `composer require symfony/cache predis/predis`
Expected: both packages are in `require`; `predis/predis` is installed.

- [ ] **Step 2: Configure the tag-aware pool**

Replace `config/packages/cache.yaml` with:

```yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: "%env(REDIS_URL)%"
        pools:
            cache.inventory:
                adapter: cache.adapter.redis_tag_aware

when@test:
    framework:
        cache:
            app: cache.adapter.array
            pools:
                cache.inventory:
                    adapter: cache.adapter.array
                    tags: true
```

- [ ] **Step 3: Write the failing integration test**

Replace `tests/Integration/Api/InventoryControllerTest.php`. Use these helpers:

```php
private function em(): EntityManagerInterface
{
    return $this->client->getContainer()->get('doctrine')->getManager();
}

private function resetDatabase(): void
{
    $em = $this->em();
    $em->getConnection()->executeStatement('DELETE FROM inventory_movement');
    $em->getConnection()->executeStatement('DELETE FROM inventory');
    $em->clear();
}

private function seedInventory(int $productId, string $onHand = '100.000', string $reserved = '10.000', string $reorder = '20.000'): int
{
    $now = new \DateTimeImmutable();
    $inventory = (new Inventory())
        ->setProductId($productId)
        ->setQuantityOnHand($onHand)
        ->setQuantityReserved($reserved)
        ->setReorderLevel($reorder)
        ->setCreatedAt($now)
        ->setUpdatedAt($now);
    $this->em()->persist($inventory);
    $this->em()->flush();

    return $inventory->getId();
}

private function body(): array
{
    return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
}
```

Required tests and assertions:

- `testIndexIsPaginatedAndOrderedByProductId`: seed product IDs 10, 20, 30; `GET /api/inventory?page=1&limit=2`; 200; body count 2; product IDs `[10, 20]`.
- `testShowByIdReturnsInventoryFields`: seed 42; `GET /api/inventory/{id}`; 200; `productId` 42, `quantityOnHand` `"100.000"`, `quantityReserved` `"10.000"`, `reorderLevel` `"20.000"`, `availableQuantity` `"90.000"`, `lowStock` false, `version` 1; `createdAt` and `updatedAt` present.
- `testShowByProductId`: seed 42; `GET /api/inventory/product/42`; 200; `productId` 42.
- `testCreateReturns201AndStoresThreeDecimals`: `POST {"productId":42,"quantityOnHand":100,"quantityReserved":"10.5","reorderLevel":"20"}`; 201; body decimals `"100.000"`, `"10.500"`, `"20.000"`; version 1; DB row exists.
- `testCreateAcceptsLargeDecimal`: `POST {"productId":43,"quantityOnHand":"999999999.999"}`; 201; body `"999999999.999"`.
- `testCreateDuplicateProductReturns409`: seed 42; POST another 42; 409; DB count 1. A failed flush closes the entity manager, so assert the count with DBAL (`SELECT COUNT(*) FROM inventory`) rather than the repository.
- `testCreateRequiresProductId`: `POST {}`; 422.
- `testCreateRejectsNegativeQuantity`: `POST {"productId":1,"quantityOnHand":-1}`; 422.
- `testCreateRejectsNonNumericQuantity`: `POST {"productId":1,"quantityOnHand":"abc"}`; 422.
- `testUpdateChangesBalancesAndBumpsVersion`: seed 42 with version 1; `PUT {"quantityOnHand":"200","version":1}`; 200; body `quantityOnHand` `"200.000"`, `quantityReserved` `"10.000"`, `version` 2.
- `testPatchKeepsOmittedFields`: seed 42; `PATCH {"reorderLevel":"30"}`; 200; `quantityOnHand` `"100.000"`, `reorderLevel` `"30.000"`.
- `testUpdateCannotChangeProductId`: seed 42; `PUT {"productId":99}`; 422; DB still 42.
- `testUpdateWithStaleVersionReturns409`: seed 42 (version 1); `PUT {"quantityOnHand":"150"}` to reach version 2; then `PUT {"version":1,"quantityOnHand":"5"}`; 409; DB still `"150.000"`.
- `testDeleteReturns204AndRemovesRow`: seed 42; `DELETE`; 204; DB null.
- `testShowMissingReturns404`, `testShowByProductMissingReturns404`, `testUpdateMissingReturns404`, `testDeleteMissingReturns404`: 404 each.
- `testLowStockReturnsItemsAtOrBelowReorderLevel`: seed 1 (`10/2/10`, available 8), seed 2 (`100/10/20`, available 90); `GET /api/inventory/low-stock`; count 1; product 1.
- `testOutOfStockReturnsZeroAvailable`: seed 3 (`5/5/0`), seed 4 (`100/10/20`); `GET /api/inventory/out-of-stock`; count 1; product 3.
- `testCachedReadIsInvalidatedByUpdate`: full test below.

```php
public function testCachedReadIsInvalidatedByUpdate(): void
{
    $this->client = static::createClient();
    $this->client->disableReboot();
    $this->resetDatabase();
    $id = $this->seedInventory(42, '10.000', '0.000', '5.000');

    $this->client->request('GET', "/api/inventory/{$id}");
    self::assertSame('10.000', $this->body()['quantityOnHand']);

    $em = $this->em();
    $em->getConnection()->executeStatement(
        'UPDATE inventory SET quantity_on_hand = ? WHERE id = ?',
        ['20.000', $id],
    );
    $em->clear();

    $this->client->request('GET', "/api/inventory/{$id}");
    self::assertSame('10.000', $this->body()['quantityOnHand']); // served from cache

    $this->client->request(
        'PUT',
        "/api/inventory/{$id}",
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode(['quantityOnHand' => '20.000']),
    );
    self::assertResponseStatusCodeSame(Response::HTTP_OK);

    $this->client->request('GET', "/api/inventory/{$id}");
    self::assertSame('20.000', $this->body()['quantityOnHand']);
}
```

- [ ] **Step 4: Run it to verify it fails**

Run: `php bin/phpunit --testsuite Integration --filter InventoryControllerTest`
Expected: FAIL — DTO classes and routes are missing, and the old fields are still in the controller.

- [ ] **Step 5: Create the two input DTOs**

`CreateInventoryInput` and `UpdateInventoryInput` are `final readonly` classes with constructor promotion. Constraints:

- `productId`: type `?int = null` on both DTOs. Create adds `#[Assert\NotNull]`; update has only `#[Assert\Positive]` and treats null as "keep". Both add `#[Assert\Positive]`.
- decimal fields: type `float|string|null`, `#[Assert\Type('numeric')]`, `#[Assert\PositiveOrZero]`; default `null` (create maps null to `0`, update keeps the current value).
- `version`: `#[Assert\Positive] ?int = null` on update.

Two DTOs are intentional: create requires `productId`; update must distinguish "omitted" from "provided" and reject a changed ID. Keeping required fields nullable with `#[Assert\NotNull]` means a missing field produces a validation `422` instead of a serializer instantiation error. This implements the spec's `InventoryInput` behavior with declarative validation.

- [ ] **Step 6: Rewrite the controller**

Use `private const READ_CONTEXT = ['groups' => ['inventory:read']];`, `CACHE_TTL = 300`, and `CACHE_TAG = 'inventory'`. Inject the pool with:

```php
public function __construct(
    #[Autowire(service: 'cache.inventory')]
    private readonly TagAwareCacheInterface $cache,
) {
}
```

Add a helper `private function cacheGet(string $key, callable $loader): string` that calls `$this->cache->get($key, function (ItemInterface $item) use ($loader): string { $item->expiresAfter(self::CACHE_TTL); $item->tag([self::CACHE_TAG]); return $loader(); });`.

Implement:

- `index(EntityManagerInterface $em, Request $request, SerializerInterface $serializer)`: paginate with `page = max(1, ...)` and `limit = max(1, min(100, ...))` default 10; cache by key; serialize `findBy([], ['productId' => 'ASC'], $limit, ($page - 1) * $limit)`.
- `showById(...)`: cache by id; `find($id)`; throw `createNotFoundException('Inventory item not found')` when missing.
- `showByProductId(...)`: cache by product ID; `findByProductId($productId)`; 404 when missing.
- `create(EntityManagerInterface $em, #[MapRequestPayload] CreateInventoryInput $input)`: guard `null === $input->productId` with a `422` (validation already rejects it), format all three decimals, set both timestamps, persist, flush inside `try/catch (UniqueConstraintViolationException)` returning `409` with `{"error": "Inventory already exists for this product."}`; invalidate the tag; return 201 with `READ_CONTEXT`.
- `update(EntityManagerInterface $em, #[MapRequestPayload] UpdateInventoryInput $input, int $id)`: `find($id)` or 404; if `productId` is present and differs, return 422; if `version` is present and differs, return 409; apply only non-null decimal fields; set `updatedAt`; flush inside `try/catch (OptimisticLockException)` returning 409; invalidate; return 200.
- `delete(EntityManagerInterface $em, Inventory $inventory)`: remove, flush, invalidate, return 204.
- `lowStock(...)` and `outOfStock(...)`: cached repository calls, serialized with `READ_CONTEXT`.
- Remove `showBySku()` and the old serializer-based create/update bodies.

- [ ] **Step 7: Rewrite the application test for the new fields**

Keep a lifecycle test that creates product 7 with `100/10/20`, shows it (`availableQuantity` `"90.000"`, `lowStock` false), patches `quantityOnHand` to `50` (available `"40.000"`), deletes it (`204`), and then gets `404`. Keep a low/out-of-stock test using product IDs 70 and 71. Movement steps are added in Task 6.

- [ ] **Step 8: Run the integration and application tests**

Run:

```bash
php bin/phpunit --testsuite Integration --filter InventoryControllerTest
php bin/phpunit --testsuite Application
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock symfony.lock config/packages/cache.yaml src/Dto src/Controller/Api/InventoryController.php tests/Integration/Api/InventoryControllerTest.php tests/Application/Api/InventoryControllerApplicationTest.php
git commit -m "feat(inventory): expose cached inventory api"
```

---

### Task 6: Movement API

**Files:**
- Create: `src/Dto/CreateInventoryMovementInput.php`
- Modify: `src/Controller/Api/InventoryController.php`
- Create: `tests/Integration/Api/InventoryMovementControllerTest.php`
- Modify: `tests/Application/Api/InventoryControllerApplicationTest.php` (add movement steps)

**Interfaces:**
- Consumes: `InventoryMovement`, `InventoryMovementType`, `Inventory::addMovement()`, `Decimal`.
- Produces:
  - `CreateInventoryMovementInput::__construct(?InventoryMovementType $type = null, float|string|null $quantity = null, ?string $referenceType = null, ?string $referenceId = null, ?string $reason = null)`
  - `GET /api/inventory/{id<\d+>}/movements?page=&limit=` (default limit 20, max 100, newest first)
  - `POST /api/inventory/{id<\d+>}/movements` (`201`)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace App\Tests\Integration\Api;

use App\Entity\Inventory;
use App\Entity\InventoryMovement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InventoryMovementControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    // em(), resetDatabase(), seedInventory(), body() as in InventoryControllerTest.

    public function testCreateMovementRecordsWithoutChangingBalances(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $id = $this->seedInventory(7, '100.000', '10.000', '20.000');

        $this->client->request(
            'POST',
            "/api/inventory/{$id}/movements",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'type' => 'sale',
                'quantity' => 5,
                'referenceType' => 'order',
                'referenceId' => 'ORD-1',
                'reason' => 'Customer order',
            ]),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $this->body();
        self::assertSame(7, $body['productId']);
        self::assertSame('sale', $body['type']);
        self::assertSame('5.000', $body['quantity']);
        self::assertSame('order', $body['referenceType']);
        self::assertSame('ORD-1', $body['referenceId']);
        self::assertSame('Customer order', $body['reason']);

        $em = $this->em();
        $em->clear();
        $inventory = $em->getRepository(Inventory::class)->find($id);
        self::assertSame('100.000', $inventory->getQuantityOnHand());
        self::assertSame('10.000', $inventory->getQuantityReserved());
        self::assertCount(1, $em->getRepository(InventoryMovement::class)->findAll());
    }
}
```

Additional required tests:

- `testListMovementsNewestFirst`: create two movements; `GET /api/inventory/{id}/movements`; 200; count 2; first ID greater than second.
- `testListMovementsIsPaginated`: create 3; `GET ...?page=1&limit=2`; count 2.
- `testMovementsForMissingInventoryReturns404` and `testCreateMovementForMissingInventoryReturns404`: 404.
- `testCreateMovementRejectsZeroQuantity`: `{"type":"sale","quantity":0}`; 422.
- `testCreateMovementRejectsUnknownType`: `{"type":"bogus","quantity":1}`; 422.
- `testCreateMovementRequiresTypeAndQuantity`: `{}`; 422.
- `testCreateMovementIgnoresPayloadProductId`: send `"productId":999`; response `productId` is the parent's.
- `testDeleteInventoryCascadesMovements`: create a movement, delete the inventory, assert the movement table is empty.

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/phpunit --testsuite Integration --filter InventoryMovementControllerTest`
Expected: FAIL — routes and DTO missing.

- [ ] **Step 3: Create the movement DTO**

`final readonly` with `?InventoryMovementType $type = null` and `#[Assert\NotNull]`, `float|string|null $quantity = null` with `#[Assert\NotNull]`, `#[Assert\Type('numeric')]`, and `#[Assert\Positive]`, `?string $referenceType` with `#[Assert\Length(max: 32)]`, `?string $referenceId` with `#[Assert\Length(max: 255)]`, and `?string $reason`. Nullable required fields keep missing payload keys on the validation path (`422`) instead of the serializer's constructor-argument path.

- [ ] **Step 4: Add the two controller actions**

- `movements(EntityManagerInterface $em, Request $request, SerializerInterface $serializer, int $id)`: `find($id)` or 404; paginate (`page = max(1, ...)`, `limit = max(1, min(100, ...))` default 20); `findBy(['inventory' => $inventory], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit, ($page - 1) * $limit)`; serialize with `READ_CONTEXT`; do not cache.
- `createMovement(EntityManagerInterface $em, #[MapRequestPayload] CreateInventoryMovementInput $input, int $id)`: guard null `type`/`quantity` with a `422`; `find($id)` or 404; build an `InventoryMovement` with `type`, `Decimal::format($input->quantity)`, references, reason, `createdAt`; `$inventory->addMovement($movement)`; persist the movement; flush; return 201 with `READ_CONTEXT`. Do not touch `quantityOnHand`/`quantityReserved` and do not invalidate the cache.

- [ ] **Step 5: Extend the application lifecycle**

Update the application test's cleanup helper to delete `inventory_movement` rows before `inventory` rows. After the patch step, `POST` a sale of `5` to `/api/inventory/{id}/movements`; assert `201`, `productId` 7, `quantity` `"5.000"`. `GET` the movements; assert count 1. `GET` the inventory; assert `quantityOnHand` is still `"50.000"` and `quantityReserved` is still `"10.000"`. Then delete and assert 404.

- [ ] **Step 6: Run the tests**

Run:

```bash
php bin/phpunit --testsuite Integration --filter InventoryMovementControllerTest
php bin/phpunit --testsuite Application
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Dto/CreateInventoryMovementInput.php src/Controller/Api/InventoryController.php tests/Integration/Api/InventoryMovementControllerTest.php tests/Application/Api/InventoryControllerApplicationTest.php
git commit -m "feat(inventory): add record-only movement api"
```

---

### Task 7: `ProductUpdated` Consumer

**Files:**
- Create: `src/Message/ProductUpdated.php`
- Create: `src/MessageHandler/ProductUpdatedHandler.php`
- Create: `config/packages/messenger.yaml`
- Modify: `Dockerfile`
- Modify: `.github/workflows/test.yml`
- Test: `tests/Integration/MessageHandler/ProductUpdatedHandlerTest.php`
- Modify: `composer.json`, `composer.lock`, `symfony.lock`

**Interfaces:**
- Consumes: `Inventory`, `InventoryRepository::findOneBy()`/`findByProductId()`, `cache.inventory`.
- Produces:
  - `App\Message\ProductUpdated::__construct(int $productId, string $sku, string $name, string $sellingPrice, string $unit)` with those five public promoted properties.
  - `App\MessageHandler\ProductUpdatedHandler::__invoke(ProductUpdated $message): void`.

- [ ] **Step 1: Install Messenger and AMQP**

Run: `composer require symfony/messenger symfony/amqp-messenger`
Expected: both packages are in `require`; the host has `ext-amqp`, so the command succeeds.

- [ ] **Step 2: Install `ext-amqp` in the runtime image**

In `Dockerfile`, keep the runtime `rabbitmq-c` package (it provides `librabbitmq.so`), add `rabbitmq-c-dev` to the build dependencies, and install the extension before `composer install`:

```dockerfile
# at the top, alongside icu-libs
RUN apk add --no-cache \
    icu-libs \
    rabbitmq-c

# replace the extension build block
RUN set -eux; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        rabbitmq-c-dev; \
    docker-php-ext-install -j"$(nproc)" \
        intl \
        pdo_mysql \
        opcache; \
    printf "\n" | pecl install amqp; \
    docker-php-ext-enable amqp; \
    apk del .build-deps
```

If `rabbitmq-c` is omitted from the runtime line, `amqp.so` fails to load after `.build-deps` is removed.

- [ ] **Step 3: Install `ext-amqp` in CI**

`composer install` checks platform requirements, so both jobs in `.github/workflows/test.yml` need the extension:

```yaml
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
            php-version: "8.4"
            extensions: amqp
            coverage: none
```

Apply the `extensions: amqp` line to the `tests` and `code-style` jobs. The test suite still uses the in-memory transport and does not contact RabbitMQ.

- [ ] **Step 4: Configure the transport**

Replace `config/packages/messenger.yaml` with:

```yaml
framework:
    messenger:
        transports:
            inventory:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange:
                        name: messages
                        type: fanout
                    queues:
                        inventory: ~
        routing:
            App\Message\ProductUpdated: inventory

when@test:
    framework:
        messenger:
            transports:
                inventory: 'in-memory://'
```

- [ ] **Step 5: Create the message class**

Mirror the product service exactly; the five public properties and their types are the wire contract. Do not rename or add required constructor arguments.

- [ ] **Step 6: Write the failing handler test**

```php
<?php

namespace App\Tests\Integration\MessageHandler;

use App\Entity\Inventory;
use App\Message\ProductUpdated;
use App\MessageHandler\ProductUpdatedHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\ItemInterface;

final class ProductUpdatedHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProductUpdatedHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->handler = self::getContainer()->get(ProductUpdatedHandler::class);
        $this->em->getConnection()->executeStatement('DELETE FROM inventory_movement');
        $this->em->getConnection()->executeStatement('DELETE FROM inventory');
        $this->em->clear();
    }

    public function testCreatesInventoryForNewProduct(): void
    {
        ($this->handler)(new ProductUpdated(501, 'SKU-501', 'Widget', '9.99', 'piece'));

        $this->em->clear();
        $inventory = $this->em->getRepository(Inventory::class)->findOneBy(['productId' => 501]);
        self::assertNotNull($inventory);
        self::assertSame('0.000', $inventory->getQuantityOnHand());
        self::assertSame('0.000', $inventory->getQuantityReserved());
        self::assertSame('0.000', $inventory->getReorderLevel());
        self::assertSame(1, $inventory->getVersion());
        self::assertNotNull($inventory->getCreatedAt());
        self::assertNotNull($inventory->getUpdatedAt());
    }

    public function testDoesNotResetExistingInventory(): void
    {
        $this->seed(502, '50.000', '5.000', '10.000');

        ($this->handler)(new ProductUpdated(502, 'SKU-502', 'Widget', '9.99', 'piece'));

        $this->em->clear();
        $inventory = $this->em->getRepository(Inventory::class)->findOneBy(['productId' => 502]);
        self::assertSame('50.000', $inventory->getQuantityOnHand());
        self::assertSame('5.000', $inventory->getQuantityReserved());
    }

    public function testDuplicateDeliveryCreatesOneRow(): void
    {
        ($this->handler)(new ProductUpdated(503, 'SKU-503', 'Widget', '9.99', 'piece'));
        ($this->handler)(new ProductUpdated(503, 'SKU-503', 'Widget', '9.99', 'piece'));

        self::assertCount(1, $this->em->getRepository(Inventory::class)->findBy(['productId' => 503]));
        self::assertCount(0, $this->em->getRepository(\App\Entity\InventoryMovement::class)->findAll());
    }

    public function testInvalidatesInventoryCache(): void
    {
        $cache = self::getContainer()->get('cache.inventory');
        $calls = 0;
        $probe = function (ItemInterface $item) use (&$calls): string {
            $item->tag(['inventory']);
            ++$calls;

            return 'probe';
        };

        $cache->get('handler_probe', $probe);
        ($this->handler)(new ProductUpdated(504, 'SKU-504', 'Widget', '9.99', 'piece'));
        $cache->get('handler_probe', $probe);

        self::assertSame(2, $calls);
    }

    private function seed(int $productId, string $onHand, string $reserved, string $reorder): void
    {
        $now = new \DateTimeImmutable();
        $inventory = (new Inventory())
            ->setProductId($productId)
            ->setQuantityOnHand($onHand)
            ->setQuantityReserved($reserved)
            ->setReorderLevel($reorder)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $this->em->persist($inventory);
        $this->em->flush();
        $this->em->clear();
    }
}
```

- [ ] **Step 7: Run it to verify it fails**

Run: `php bin/phpunit --testsuite Integration --filter ProductUpdatedHandlerTest`
Expected: FAIL — the message/handler classes do not exist.

- [ ] **Step 8: Implement the handler**

Inject `ManagerRegistry` and the cache pool:

```php
public function __construct(
    private readonly ManagerRegistry $registry,
    #[Autowire(service: 'cache.inventory')]
    private readonly TagAwareCacheInterface $cache,
) {
}
```

On invocation: get the manager; if an `Inventory` with that `productId` exists, return. Otherwise create it with `productId`, all decimals `'0.000'`, both timestamps, persist, and flush. Catch `UniqueConstraintViolationException`, call `$this->registry->resetManager()`, and return (the duplicate won the race; the entity manager from the failed flush is closed). On success, `invalidateTags(['inventory'])`. Never create a movement.

- [ ] **Step 9: Run it to verify it passes**

Run: `php bin/phpunit --testsuite Integration --filter ProductUpdatedHandlerTest`
Expected: PASS (4 tests).

- [ ] **Step 10: Verify the messenger wiring**

Run:

```bash
php bin/console debug:config framework messenger
php bin/console debug:config framework messenger --env=test
php bin/console lint:container
```

Expected: dev shows the `inventory` AMQP transport with the `messages` fanout exchange and `inventory` queue; test shows `inventory: 'in-memory://'`; routing maps `App\Message\ProductUpdated` to `inventory`; lint passes.

- [ ] **Step 11: Commit**

```bash
git add composer.json composer.lock symfony.lock config/packages/messenger.yaml src/Message src/MessageHandler tests/Integration/MessageHandler Dockerfile .github/workflows/test.yml
git commit -m "feat(inventory): consume product updated events"
```

---

### Task 8: Worker Deployment

**Files:**
- Modify: `docker-compose.inventory.yml` (repo root: `../../docker-compose.inventory.yml`)

**Interfaces:**
- Consumes: the `inventory` transport and `cache.inventory` pool from Tasks 5 and 7.
- Produces: an `inventory-worker` service running `messenger:consume inventory`; inventory containers reach Redis and RabbitMQ by service name.

- [ ] **Step 1: Fix the inventory service environment and dependencies**

In `inventory-service`:

- change `REDIS_URL` to `redis://inventory-cache-redis:6379`;
- add `MESSENGER_TRANSPORT_DSN: "amqp://${AUTH_RABITMQ_USER}:${AUTH_RABITMQ_PASSWORD}@rabbitmq:5672/%2f"`;
- add `inventory-cache-redis: { condition: service_healthy }` to `depends_on`.

- [ ] **Step 2: Add the worker service**

```yaml
  inventory-worker:
    build:
      context: ./services/inventory-service
      additional_contexts:
        infra: ./infrastructure
    restart: unless-stopped
    user: www-data
    depends_on:
      inventory-db:
        condition: service_healthy
      inventory-cache-redis:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy
    environment:
      APP_ENV: dev
      REDIS_URL: 'redis://inventory-cache-redis:6379'
      MESSENGER_TRANSPORT_DSN: "amqp://${AUTH_RABITMQ_USER}:${AUTH_RABITMQ_PASSWORD}@rabbitmq:5672/%2f"
      DATABASE_URL: "mysql://${INVENTORY_DB_USER}:${INVENTORY_DB_PASSWORD}@inventory-db:3306/${INVENTORY_DB_NAME}?serverVersion=8.4&charset=utf8mb4"
    command: ["php", "bin/console", "messenger:consume", "inventory", "--time-limit=3600", "--memory-limit=128M", "-vv"]
    networks:
      - microservice_net
```

The image has no `ENTRYPOINT`, so `command` replaces the default `php-fpm`. Do not use `docker/supervisord.conf`.

- [ ] **Step 3: Validate the compose files**

From the repository root (`erp/`):

```bash
docker compose -f docker-compose.yml config >/dev/null
```

Expected: exit 0. The root file includes `docker-compose.infra.yml` (which defines `rabbitmq`) and `docker-compose.inventory.yml`.

- [ ] **Step 4: Optionally verify the image builds with AMQP**

Run: `docker compose -f docker-compose.yml build inventory-service`
Expected: the image builds and `pecl install amqp` succeeds. This is the only check that proves the Dockerfile change; run it when Docker is available.

- [ ] **Step 5: Commit**

Run from the inventory-service directory; the compose file is two levels up:

```bash
git -C ../.. add docker-compose.inventory.yml
git -C ../.. commit -m "chore(inventory): run the product updated worker"
```

---

### Task 9: Final Verification

**Files:** none (verification only; commit formatting fixes if any).

- [ ] **Step 1: Recreate the test schema and run the whole inventory suite**

Run:

```bash
rm -f var/data_test.db
php bin/console doctrine:schema:create --env=test
php bin/phpunit
```

Expected: all Unit, Integration, and Application tests pass; record the test and assertion counts.

- [ ] **Step 2: Check style and configuration**

Run:

```bash
vendor/bin/php-cs-fixer fix --diff
php bin/console lint:container
php bin/console lint:yaml config/
php bin/console doctrine:schema:validate
```

Expected: php-cs-fixer leaves only the intended changes; lint and schema validation pass. If php-cs-fixer changes files, rerun `php bin/phpunit`.

- [ ] **Step 3: Run the product-service regression suite**

Run:

```bash
cd ../product-service
php bin/phpunit
```

Expected: the product-service suite passes (11 tests / 41 assertions as of the completed product-service fix). The product-service changes are uncommitted; do not revert them.

- [ ] **Step 4: Check the diff**

Run:

```bash
git diff --check
git status --short
git -C ../.. diff --check docker-compose.inventory.yml
git -C ../.. status --short docker-compose.inventory.yml
```

Expected: no whitespace errors; only the intended inventory files (plus the pre-existing untracked scaffold) are changed in the service repo, and only `docker-compose.inventory.yml` is changed for this feature in the root repo.

- [ ] **Step 5: Commit any formatting-only changes**

If Step 2 changed files:

```bash
git add -u
git commit -m "style(inventory): apply project formatting"
```

Do not claim completion without the output of the commands above.

---

## Amendment A: audited inventory writes and soft delete (2026-10-29, product owner)

This amendment supersedes the record-only wording above for inventory
create/update/delete and the hard-delete/cascade behavior. It is a
product-owner change made after the final review, not implementation drift. The
explicit movement endpoint and `ProductUpdatedHandler` stay movement-free.

### A1. Schema and entities

- [ ] New migration after `Version20261029014814`:
  - `inventory.deleted_at DATETIME NULL`;
  - `inventory_movement.quantity_delta`, `balance_after`, `reserved_delta`,
    `reserved_after`, each `NUMERIC(12, 3) NULL`.
  - `down()` throws `IrreversibleMigration` (audit state cannot be rebuilt);
    set a description.
- [ ] `Inventory`: nullable `deletedAt` with getter/setter, no serializer group.
- [ ] `InventoryMovement`: four nullable decimal fields with getters/setters,
  all in `inventory:read`.
- [ ] Recreate the SQLite test schema from the mapping after the change.

### A2. Repository

- [ ] Add `findActive(int $id)`, `findActiveByProductId(int $productId)`, and
  `findActivePage(int $limit, int $offset)`.
- [ ] Add `i.deletedAt IS NULL` to `findLowStock()` and `findOutOfStock()`.
- [ ] Keep `findByProductId()` unfiltered for create restore and the handler.

### A3. Controller

- [ ] One private helper appends an automatic `adjustment` movement from a
  before-snapshot: signed deltas via `Decimal::subtract`, zero check via
  `Decimal::compare`, and `quantity = abs(onHandDelta)` when on-hand changed,
  otherwise `abs(reservedDelta)`.
- [ ] `create()`: active duplicate `409`; soft-deleted row restored in place
  (same id, balances/reorder from request, `deletedAt = null`, original
  `createdAt`, `updatedAt` now); initial movement from zero when nonzero; one
  flush; invalidate; `201`. Unique-constraint race still `409`.
- [ ] `update()`: snapshot before, apply supplied fields, append one movement
  only when on-hand or reserved changed; keep the version pre-check and
  `OptimisticLockException -> 409`; no movement persisted on failure; invalidate
  after flush.
- [ ] `delete()`: `#[MapEntity(expr: 'repository.findActive(id)')]`; append the
  delete movement (`-oldOnHand`/`-oldReserved`, after `0.000`), zero balances,
  set `deletedAt`/`updatedAt`; flush; invalidate; `204`. Second delete `404`.
- [ ] `POST /movements`: `#[MapEntity(expr: 'repository.findActive(id)')]`;
  unchanged record-only body; null delta fields; no invalidation.
- [ ] `index`/`show`/`show by product`/`update` use active-only reads; the
  movements history keeps the unfiltered `find($id)`, so a soft-deleted
  inventory still exposes its history.
- [ ] `ProductUpdatedHandler`: active row no-op; soft-deleted row restored with
  zero balances, `deletedAt = null`, `updatedAt` now, invalidate, no movement;
  missing row as before (zero row, invalidate, no movement).

### A4. Tests (write first, watch red, then implement)

- [ ] Create nonzero -> one initial movement with deltas/after, reason and
  reference type; zero/zero -> none.
- [ ] Update on-hand increase/decrease -> signed delta and after; reserved-only
  -> reserved delta; reorder-only -> none; stale version -> none.
- [ ] Delete -> soft delete, zeroed balances, delete movement with negative
  deltas and zero after, show/list excluded, history still returns all
  movements, second delete `404`.
- [ ] Create after delete -> same id, old movements kept, new initial movement.
- [ ] Handler restores a soft-deleted row with zero balances and no movement.
- [ ] Explicit movement POST stays balance-neutral, delta fields null, no
  inventory cache invalidation.

### A5. Verification

- [ ] `php bin/phpunit` (full suite, exact counts recorded).
- [ ] `vendor/bin/php-cs-fixer fix --diff`.
- [ ] `php bin/console lint:container`.
- [ ] `php bin/console lint:yaml config/`.
- [ ] `php bin/console doctrine:schema:validate`.
- [ ] Report in `.superpowers/sdd/2026-10-29-inventory-movements/inventory-write-audit-report.md`
  plus a ledger entry. Do not claim the final review passed.
