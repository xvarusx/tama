# Design: Migrate all service entity IDs to UUID (2026-11-18)

## Intent
Every Symfony service entity PK that is currently `int` auto-increment becomes a UUID
primary key, following the existing payment/invoice pattern. Auth user IDs also become
UUID, including all cross-service references. After the schema is corrected, load fixtures
into all dev DBs so the frontend shows data.

## Decisions (confirmed with partner)
1. **Full UUID everywhere**: authentication-service `User.id` → UUID, and every
   cross-service reference to a user (notification `recipientId`/`userId`/`userIds`,
   contact `createdBy`/`updatedBy`/`blacklistedBy`/`userId`) → UUID.
2. **Cross-service reference IDs → UUID too**: int business reference IDs (inventory
   `productId`, etc.) become UUID strings. IDs already string/UUID stay as-is.
3. Corrective migrations per service (not `schema:update --force`).
4. Then reload fixtures into every dev DB.

## Canonical UUID pattern (from payment/invoice)
Entity:
```php
use Symfony\Component\Uid\Uuid;
#[ORM\Id]
#[ORM\Column(type: 'uuid')]        // or Types::GUID — use 'uuid' consistently
private Uuid $id;
// constructor: $this->id = Uuid::v7();
public function getId(): Uuid { return $this->id; }
```
- Repository: `findActiveById(Uuid|string $id)` → `Uuid::fromString()` + `$this->find($uuid)`;
  DQL filters use `->setParameter('x', $uuid, 'uuid')`.
- Controller: `#[Route('/{id}')]` with UUID regex requirement (or none, like payment),
  param `string $id`, convert via `Uuid::fromString()`.
- Mapper: `id: $entity->getId()->toRfc4122()`; resource/document `public string $id,`.
- Migration: `addColumn('id', 'uuid', ['autoincrement' => false]); setPrimaryKey(['id']);`.
- Fixtures rely on constructor-generated ids; FK ids use literal UUID strings / `Uuid::v7()`.

## Per-service scope

### authentication-service (foundational)
- `User::$id` → UUID v7 (add constructor init). No GeneratedValue.
- `/me`, `/internal/users`, login success, email-verification: expose `id` as UUID string.
- `/internal/users?ids=` accepts comma-separated UUIDs (drop intval/ctype_digit/positive-int checks).
- `UserRepository::findVerifiedByIds(array $ids)` → UUID strings.
- Update all auth tests + fixtures.

### contact-service
- `Contact.id`, `ContactAuditEntry.id`/`contactId`, `OutboxEvent.id` → UUID.
- `createdBy`/`updatedBy`/`blacklistedBy` → UUID string (or nullable UUID).
- Security: `ContactUser::$id` → UUID string; `AuthenticationServiceAuthenticator` drop
  `is_int($userData['id'])` gate, validate UUID string instead.
- Controllers: drop `Requirement::DIGITS`, use UUID regex; `int $id` → `string $id`;
  `getCurrentUserId(): string`. Mappers/resources/docs → string ids.
- Reconcile schema: migrations already have `contacts.id BINARY(16)` — align to `uuid`.

### notification-service
- `NotificationPreference.id`, `Setting.id` → UUID.
- `Notification.recipientId`, `NotificationPreference.userId`, `Caller::$id`,
  `Recipient::$id`, `NotificationRequested.userIds` (list<int> → list<string>) → UUID.
- `AuthServiceClient`: remove all `(int)` casts on ids; pass UUID strings in `ids=`.
- Repositories: DQL filters on recipient/user id with `'uuid'` type.
- Controllers/mappers/docs: recipientId string.
- Events/fixtures/tests: UUID user ids.

### product-service
- `Product`, `Brand`, `Unit`, `Category`, `ProductVariant`, `ProductMedia`, `OutboxEvent`
  PKs → UUID.
- All FK relations (product.brand/unit/category, variant.product, media.product) stay ORM
  relations but join columns become `uuid`.
- DTOs `brandId`/`unitId`/`categoryId`/`ignoreId`/`VariantInput.id` → string (UUID).
- Mappers/resources/docs `public int $id` → `public string $id`, remove `(int)` casts.
- Controllers: drop `\d+` requirements, `int $id` → `string $id`, `Uuid::fromString()`.
- `SlugGenerator::generate(..., ?int $ignoreId)` → `?string`; `ReferenceNotFoundException`
  `int $id` → `string`. Events/messages: ids as `->toRfc4122()` strings.
- `MediaStorage`: product id path uses UUID string (drop `\d+` regex assumption).

### inventory-service
- `Inventory.id`, `InventoryMovement.id`, local `Product.id` → UUID (Product drops manual
  `setId(int)`, gets constructor UUID v7 like others).
- `productId` (cross-service product id) → string UUID; `InventoryRepository` filters update.
- `InventoryMovement.inventory_id` FK → uuid.
- Controllers drop `\d+`, `int` → `string`; mappers/resources/docs → string ids.
- `StockMovementService` int id params → string UUID. Events/handlers update.

### order-service
- `Order.id` → UUID v7. **Keep `getUuid()` as an alias returning `$this->id`** so all event
  payloads (`'orderId' => (string) $this->order->getUuid()`) keep working unchanged.
- `OrderLine.id`, `OutboxEvent.id` → UUID; `order_id` FK → uuid.
- `OrderLine.productId` already UUID (string entity field int? it's `Uuid`) — keep.
- Controllers: `int $id` → `string $id`, add UUID handling; mappers/resources/docs → string.
- Note: order-service currently has a separate routing bug (0 routes) — agent should verify
  routes register after the migration.

### frontend
- `products-api.tsx`, `inventory-api.tsx`, `auth-api.tsx` types: `number` → `string` for all
  entity/ref ids (Brand/Unit/Category/Variant/Media/Product/Inventory/InventoryMovement/
  AuthUser + brandId/unitId/categoryId/mediaId/productId).
- `product-validation.ts` `toId(): number` → string (no parseInt); `VariantInput.id`.
- `products/show.tsx`, `inventory/show.tsx`: `Number(id)` → raw string id.
- `products/page.tsx`: numeric filters → string.
- `product-form-sheet.tsx` `removeExistingImage(mediaId: number)` → string.
- Update `products-api.test.ts`, `product-validation.test.ts`.
- Contacts `createdBy/updatedBy` number → string.

## Non-goals
- No changes to already-UUID payment/invoice entities.
- No behavior changes beyond id typing.
- Do not touch docker-compose files or gateway.

## Migration & fixture plan (after code changes)
For each service: create a corrective migration that drops/adds the id and FK columns to
`uuid`, re-run migrations on the dev DB, then `doctrine:fixtures:load --no-interaction` to
seed 25+ rows/tables. For contact, also drop the stale `client` table and `trust_level` column.

## Verification
- Each service: `php -l`, `lint:container`, `doctrine:schema:validate`, `debug:router`, full phpunit.
- Frontend: lint + all tests.
- `docker compose config --quiet`.
- Recreate all app containers; load fixtures; confirm row counts > 0 in every DB; browser-check
  that contact/inventory/products/payments/invoice/notification pages render data with no console errors.
