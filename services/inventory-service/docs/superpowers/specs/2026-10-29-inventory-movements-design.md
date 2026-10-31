# Inventory Movements and Product Sync — Design

- **Service:** inventory-service
- **Date:** 2026-10-29
- **Status:** approved (2026-10-29); amended after final review (2026-10-29)

## Amendment: audited inventory writes (2026-10-29, product owner)

This amendment is a product-owner design change made after the final review. It
supersedes every conflicting statement below, in particular "movements do not
change balances" as applied to inventory create/update/delete, and "deleting an
inventory deletes its movements".

### Audit semantics

Every inventory write that changes stock now appends exactly one `adjustment`
movement in the same transaction as the inventory write:

- **Create** with a nonzero on-hand or reserved balance: one movement with
  `quantityDelta`/`balanceAfter` and `reservedDelta`/`reservedAfter` measured
  from zero, `reason: "Inventory created"`, `referenceType: inventory`.
  A zero/zero create appends no movement.
- **Update** where `quantityOnHand` or `quantityReserved` actually changes: one
  movement with signed deltas and the new balances, `reason: "Inventory
  updated"`. Reorder-level-only and no-op updates append nothing.
- **Delete**: one movement with deltas `-oldOnHand` / `-oldReserved` and
  balance-after `0.000` / reserved-after `0.000`, `reason: "Inventory
  deleted"`.

The existing positive `quantity` column stays and keeps its meaning for
explicit movements. For automatic movements it is `abs(quantityDelta)` when the
on-hand balance changed, otherwise `abs(reservedDelta)`.

`quantityDelta`, `balanceAfter`, `reservedDelta`, and `reservedAfter` are
nullable `DECIMAL(12,3)` columns on `inventory_movement`; they stay `null` for
movements created through `POST /api/inventory/{id}/movements`.
`InventoryMovement` exposes getters/setters for all four and serializes them in
`inventory:read`.

### Soft delete

`Inventory` gains a nullable `deletedAt` timestamp (not serialized).

- `DELETE` zeroes both balances, appends the delete movement, sets `deletedAt`,
  and flushes. The inventory row and every movement row remain.
- Inventory reads (`index`, `show`, `show by product`, `low-stock`,
  `out-of-stock`) and `PUT`/`PATCH` exclude soft-deleted rows; `GET
  /api/inventory/{id}/movements` still returns the full history for a
  soft-deleted inventory. `POST /api/inventory/{id}/movements` on a
  soft-deleted inventory returns `404` before payload validation.
- Deleting an already soft-deleted inventory returns `404`.
- `POST /api/inventory` for a `productId` that has a soft-deleted row restores
  that same row (same `id`; `deletedAt = null`; balances and reorder level from
  the request; `updatedAt` now), keeps the original `createdAt` and all old
  movements, and appends the initial movement measured from zero. It returns
  `201`; it never inserts a second row. An active duplicate still returns
  `409`.

### Repository

`InventoryRepository` gains active-only reads:

- `findActive(int $id)` and `findActiveByProductId(int $productId)`;
- `findActivePage(int $limit, int $offset)` for `index()`;
- `findLowStock()` / `findOutOfStock()` add `i.deletedAt IS NULL`.

Only the movement-history lookup uses the unfiltered `find($id)`; everything
that mutates or shows an inventory requires an active row. `DELETE` and
`POST /movements` use `#[MapEntity(expr: 'repository.findActive(id)')]`, which
keeps the 404-before-payload-validation ordering.

### Product sync

`ProductUpdatedHandler` stays movement-free:

- active row: no-op;
- soft-deleted row: restore it with `deletedAt = null`, zero balances,
  `updatedAt` now, flush, invalidate the `inventory` tag, create no movement;
- missing row: create a zero-balance row, flush, invalidate the tag, create no
  movement (unique-race branch unchanged).

### Caching

Create (including restore), update, and delete invalidate the `inventory` tag
after a successful flush. Explicit movement creation still changes no balances
and does not invalidate inventory caches. Handler restore invalidates the tag.

### Migration

A new migration after `Version20261029014814` adds nullable
`inventory.deleted_at` and nullable `inventory_movement.quantity_delta`,
`balance_after`, `reserved_delta`, `reserved_after` (`DECIMAL(12,3)`). Its
`down()` is explicitly irreversible: the automatic movements encode audit state
that cannot be reconstructed after dropping the columns, so the migration is
up-only by design.

### Testing

The suite covers: create with nonzero balances (one initial movement) and
zero/zero (none); create restoring a soft-deleted row (same id, old movements
kept, new initial movement); update on-hand increase/decrease, reserved-only,
reorder-only, and stale-version conflict (movement only for balance changes);
delete (soft-delete, reads excluded, history readable, negative deltas, zero
after, second delete `404`); explicit movement POST remaining balance-neutral
with null delta fields and no cache invalidation; handler restore with zero
balances and no movement. Existing tests that assumed no automatic movement or
a hard-delete cascade are updated.

### Decisions

- Restore keeps the original `createdAt`; it is the row's first creation time,
  and `updatedAt` records the restore.
- A delete always appends the delete movement, even for an already-zero row:
  the movement records the removal event (quantity `0.000` in that edge case).
- Automatic movements leave `referenceId` null; `referenceType` is `inventory`.
- Automatic movement construction lives in one controller helper using
  `Decimal::subtract`/`compare` and an absolute-value helper; no floats or
  bcmath.

## Intent

The inventory service currently models product metadata (`sku`, `name`, `description`, `location`) and whole-number stock levels. It needs to become the stock ledger for the product service: one inventory record per product, exact decimal balances, an audit trail of stock movements, cached reads, and a consumer for the product service's `ProductUpdated` message.

## Goals

- Reshape `Inventory` around `productId`, decimal balances, and optimistic locking.
- Add `InventoryMovement` as an audit trail, one-to-many from `Inventory`.
- Update the API to the new fields, add movement endpoints, and cache reads with tag invalidation.
- Consume `App\Message\ProductUpdated` and ensure an `Inventory` row exists for the product.
- Ship a Doctrine migration and tests that exercise the HTTP API and the handler.

## Non-goals

- Movements do not change balances. They are records only; stock changes go through the inventory update endpoint.
- No local copy of product name, SKU, price, or unit. The inventory service only stores `productId`.
- No HTTP calls from inventory to product service.
- No authentication, UI, or reporting endpoints beyond the existing low-stock / out-of-stock queries.

## Anti-goals

- A shared consumer queue: product and inventory would compete for messages instead of both receiving them.
- Floats as the storage type for stock: precision loss on `DECIMAL(12,3)` values.
- Silent balance changes on movement creation.
- Movement rows that disagree with their parent inventory's `productId`.

## Constraints

- Symfony 8.1, PHP >= 8.4, Doctrine ORM 3.7, MySQL 8 in dev/prod, SQLite in tests.
- Product IDs are integers (product-service uses auto-increment integer IDs).
- `ProductUpdated` is published with the framework's default `PhpSerializer`, so the inventory service must declare a class with the **same FQCN** (`App\Message\ProductUpdated`) and the same public properties: `productId`, `sku`, `name`, `sellingPrice`, `unit`.
- The product service publishes to the `messages` exchange (fanout) and consumes its own `messages` queue. Inventory must declare a separate `inventory` queue bound to that exchange.
- Messenger, AMQP transport, cache, and Predis are not installed yet. Install with Flex.
- The runtime image does not include `ext-amqp`; add `rabbitmq-c-dev` + `pecl install amqp` to the Dockerfile before `composer install`, or the transport cannot load.
- Redis is already configured in `.env` (`REDIS_URL`) but unused. Tests must not require Redis or RabbitMQ.

## Approaches considered

### Caching

1. **Tag-aware Redis pool `cache.inventory` (chosen).** Mirrors the product service. Reads are cached per key, writes invalidate the `inventory` tag. Test environment overrides the pool with an array adapter wrapped as tag-aware.
2. Symfony HTTP cache. Too coarse for per-record invalidation and complicates writes.
3. No cache. Rejected because caching was requested.

### Movement API

1. **Nested under the inventory record (chosen):** `GET`/`POST /api/inventory/{id}/movements`. Ownership is explicit and `productId` can be derived from the parent.
2. Top-level `/api/inventory-movements` CRUD. More routes, and clients can create orphan or mismatched movements.
3. Entity only, no endpoints. Leaves the audit trail unusable.

### Product sync

1. **Dedicated `inventory` queue on the shared `messages` fanout exchange (chosen).** Product keeps its `messages` queue; inventory gets a copy of every `ProductUpdated`.
2. Same queue name `messages` in both services. Rejected: messages would be load-balanced between services.
3. HTTP polling from inventory. Rejected: unnecessary coupling and latency.

### Movement effect

1. **Record-only (chosen, confirmed by the product owner).** Balances change only through `PUT`/`PATCH /api/inventory/{id}`.
2. Apply movements to balances. Rejected: type semantics become ambiguous (a negative sale vs. a signed quantity) and writes mutate stock implicitly.

## Entity design

### `App\Entity\Inventory`

| Field | Mapping | Type |
|---|---|---|
| `id` | `#[ORM\Id]`, `#[ORM\GeneratedValue]`, `#[ORM\Column]` | `?int` |
| `productId` | `#[ORM\Column(unique: true)]` | `?int` |
| `quantityOnHand` | `#[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]` | `?string` |
| `quantityReserved` | same | `?string` |
| `reorderLevel` | same | `?string` |
| `createdAt` | `#[ORM\Column]` | `?\DateTimeImmutable` |
| `updatedAt` | `#[ORM\Column]` | `?\DateTimeImmutable` |
| `version` | `#[ORM\Version]`, `#[ORM\Column]` | `int` (default 1) |
| `movements` | `#[ORM\OneToMany(mappedBy: 'inventory', cascade: ['remove'], orphanRemoval: true)]` | `Collection<int, InventoryMovement>` |

Derived helpers: `getAvailableQuantity(): string` (`quantityOnHand - quantityReserved`) and `isLowStock(): bool` (`available <= reorderLevel`). Quantity arithmetic is done with an integer-thousandths helper (see Decisions) to avoid float drift and to avoid `ext-bcmath`.

Removed fields: `sku`, `name`, `description`, `quantity`, `reserved_quantity`, `min_stock_level`, `location`.

Deleting an inventory is a soft delete: the row keeps its movements (see the
amendment; the historical cascade mapping is no longer exercised).

### `App\Entity\InventoryMovement`

| Field | Mapping | Type |
|---|---|---|
| `id` | `#[ORM\Id]`, `#[ORM\GeneratedValue]`, `#[ORM\Column]` | `?int` |
| `inventory` | `#[ORM\ManyToOne(inversedBy: 'movements')]`, `#[ORM\JoinColumn(nullable: false)]` | `Inventory` |
| `productId` | `#[ORM\Column]` | `?int` (copied from the parent on creation) |
| `type` | `#[ORM\Column(length: 32, enumType: InventoryMovementType::class)]` | `InventoryMovementType` |
| `quantity` | `#[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]` | `?string` |
| `referenceType` | `#[ORM\Column(length: 32, nullable: true)]` | `?string` |
| `referenceId` | `#[ORM\Column(length: 255, nullable: true)]` | `?string` |
| `reason` | `#[ORM\Column(type: Types::TEXT, nullable: true)]` | `?string` |
| `createdAt` | `#[ORM\Column]` | `?\DateTimeImmutable` |

`InventoryMovementType` is a string-backed enum: `purchase_receipt`, `sale`, `return`, `adjustment`.

### Repository

`InventoryRepository` keeps `findByProductId(int): ?Inventory`, `findLowStock()`, and `findOutOfStock()`, rewritten against the new columns. Low stock is `quantityOnHand - quantityReserved <= reorderLevel`; out of stock is `quantityOnHand - quantityReserved <= 0`.

## API design

All responses are JSON. Decimal fields are serialized as strings (e.g. `"100.000"`).

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/inventory?page=&limit=` | Paginated list, ordered by `productId`; `page >= 1`, `1 <= limit <= 100` |
| `GET` | `/api/inventory/{id<\d+>}` | Show by inventory id |
| `GET` | `/api/inventory/product/{productId<\d+>}` | Show by product id |
| `POST` | `/api/inventory` | Create; `201` |
| `PUT`/`PATCH` | `/api/inventory/{id<\d+>}` | Update; `200` |
| `DELETE` | `/api/inventory/{id<\d+>}` | Delete; `204` |
| `GET` | `/api/inventory/low-stock` | Items at or below reorder level |
| `GET` | `/api/inventory/out-of-stock` | Items with no available stock |
| `GET` | `/api/inventory/{id<\d+>}/movements?page=&limit=` | Movements for an inventory, newest first |
| `POST` | `/api/inventory/{id<\d+>}/movements` | Append a movement; `201` |

The old `/api/inventory/sku/{sku}` route is removed.

Request payloads use `#[MapRequestPayload]` with readonly input DTOs:

- `InventoryInput`: `productId` (`#[Assert\Positive]`), `quantityOnHand`, `quantityReserved`, `reorderLevel` (all `#[Assert\PositiveOrZero]`), decimals accepted as JSON numbers or numeric strings and stored as 3-decimal strings.
- `InventoryMovementInput`: `type` (enum), `quantity` (`#[Assert\Positive]`), optional `referenceType`, `referenceId`, `reason`. `productId` is always taken from the parent inventory; a mismatching value in the payload is ignored.

`PATCH` reuses the same DTO; omitted fields keep their current value. `PUT` and `PATCH` are treated identically for now, matching the existing service.

Validation failures and malformed JSON return `422` through `#[MapRequestPayload]`. Missing records return `404`. A duplicate `productId` on create returns `409` (the unique column is caught and mapped), and changing `productId` on update returns `422`.

### Response shape (confirmed 2026-10-29)

- Entities are serialized with a `#[Groups(['inventory:read'])]` group, following the product service pattern:
  - `Inventory`: `id`, `productId`, `quantityOnHand`, `quantityReserved`, `reorderLevel`, `createdAt`, `updatedAt`, `version`, plus derived `availableQuantity` and `lowStock` (read-only getters in the group; `isLowStock()` serializes as `lowStock`).
  - `InventoryMovement`: `id`, `productId`, `type`, `quantity`, `referenceType`, `referenceId`, `reason`, `createdAt`.
- No nesting: inventory reads do not embed `movements`, and movement reads do not embed `inventory`. This keeps cached inventory payloads stable and avoids circular references.
- Lists are bare arrays, matching the current service and the product service. Movement lists are paginated.

## Caching

- Pool: `cache.inventory`, `cache.adapter.redis_tag_aware`, provider `%env(REDIS_URL)%`.
- Keys: `inventory_page_{page}_limit_{limit}`, `inventory_id_{id}`, `inventory_product_{productId}`, `inventory_low_stock`, `inventory_out_of_stock`.
- Tag: `inventory`. TTL 300 seconds.
- Create, update, delete, and a handler-created row invalidate the `inventory` tag.
- Movement reads are not cached (audit data); movement creation does not invalidate inventory caches because it does not change balances.
- Deployment fix: `docker-compose.inventory.yml` currently sets `REDIS_URL: 'redis://redis:6379'` but the Redis service is named `inventory-cache-redis`. Fix the URL (or add a `redis` network alias) and add the dependency; otherwise the pool 500s inside Docker. Local `.env` keeps `redis://127.0.0.1:6379`.

## Product sync

- Add `src/Message/ProductUpdated.php`, mirroring the product service's class exactly (same FQCN and public properties).
- Add `src/MessageHandler/ProductUpdatedHandler.php` with `#[AsMessageHandler]`. It looks up `Inventory` by `productId`:
  - exists → do nothing;
  - missing → persist `Inventory(productId, quantityOnHand: "0.000", quantityReserved: "0.000", reorderLevel: "0.000", createdAt, updatedAt)` and flush.
- A duplicate delivery can race; the unique `productId` column is the guarantee. Catch `UniqueConstraintViolationException` and treat it as already handled.
- After creating a row, invalidate the `inventory` tag.
- Messenger transport `inventory` uses the existing `MESSENGER_TRANSPORT_DSN` with `queues: { inventory: {} }` and routes `App\Message\ProductUpdated` to it. The `messages` exchange is fanout, so both services receive the event. Test environment uses `in-memory://`.
- The runtime Dockerfile ends in `CMD ["php-fpm"]` and does not install Supervisor, so `docker/supervisord.conf` cannot run the consumer. **Confirmed:** add a separate `inventory-worker` service to the root `docker-compose.inventory.yml` that reuses the app image with `command: php bin/console messenger:consume inventory --time-limit=3600 --memory-limit=128M`, plus the RabbitMQ environment variables. The image must also install `ext-amqp` (`rabbitmq-c-dev` + `pecl install amqp`), or the AMQP transport cannot start.

## Migration

One migration generated from the entity mapping:

- create `inventory_movement` with its foreign key and indexes;
- add `product_id`, `quantity_on_hand`, `quantity_reserved`, `reorder_level`, `version` to `inventory`;
- drop `sku`, `name`, `description`, `quantity`, `reserved_quantity`, `min_stock_level`, `location`.

The dev database `inventories_db` does not exist locally; it will be created before migrating. Existing rows (only fixtures) are not preserved. The migration is the source of truth; tests use `doctrine:schema:create` in CI, and the local SQLite test database is recreated from the mapping whenever the schema changes.

## Testing

- **Unit:** available quantity and low-stock thresholds with decimal strings; movement type enum.
- **Integration:** CRUD against the new fields; lookup by product id; low/out-of-stock; movement create/list; cache invalidation (read, update, read again); handler creates a row once, is idempotent, and creates no movement.
- **Application:** full lifecycle (create → show → patch → movement → delete) through HTTP.
- Tests use `#[MapRequestPayload]` payload shapes and assert decimal strings.

## Decisions made here

- Integer IDs, integer `productId`, unique per inventory.
- Decimal values stored as strings, `DECIMAL(12,3)`.
- Explicit movements are record-only; inventory create/update/delete also append
  audited adjustment movements (amendments).
- Movement `productId` is derived from the parent.
- Deleting an inventory is a soft delete that keeps its movements.
- Decimal payloads accepted as numbers or strings.
- Test suite stays broker-free and Redis-free.
- `productId` is immutable on update (`422` if it differs); duplicate create is `409`.
- Optimistic locking: `version` is returned in responses and may be sent on update. A mismatching version is `409`; Doctrine's `OptimisticLockException` is also mapped to `409`.
- No `ext-bcmath`: decimal sums use an integer-thousandths helper (`DECIMAL(12,3)` fits comfortably in an integer), so the runtime image does not change.
- Product creation is out of scope: the product service only dispatches `ProductUpdated` on update. New products get an inventory row on their first update, not on creation. Documented limitation; no product-service change.

## Resolved questions (2026-10-29)

1. Response shape: entity-shaped with `#[Groups]`, no embedded relationships, bare-array lists. Confirmed.
2. Worker deployment: separate `inventory-worker` compose service. Confirmed.

## Left to the builder

- Exact migration handling if a database with real rows is found (the design assumes fixtures only).
- Pagination defaults: movement lists use `page = 1`, `limit = 20`, max 100.
- Low-stock uses `<=`, matching the existing code.
- Handler duplicates are handled silently (no extra logging).
