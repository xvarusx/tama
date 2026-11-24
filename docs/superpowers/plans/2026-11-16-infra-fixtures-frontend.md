# Compose, Fixtures & Frontend Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add root compose coverage for order/notification, seed 30 rows per table for 5 services, and fix the Payments submenu + user identity display.

**Architecture:** Three independent subsystems. Compose files follow the existing per-service include pattern (`microservice_net` bridge, named volumes). Fixtures are Doctrine `Fixture` classes using domain methods/constructors. Frontend uses React Router + shadcn sidebar primitives.

**Tech Stack:** Symfony 8.1 / PHP 8.4 / Doctrine ORM 3, MySQL 8.4, Redis 7, React 19 + TypeScript + Vite.

**Spec:** `docs/superpowers/specs/2026-11-16-infra-fixtures-frontend-design.md`

## Global Constraints
- 30 rows per table (more than 25). Respect all unique constraints; vary the constrained values.
- Order-service and notification-service DB = MySQL 8.4; invoice-service fixtures target its configured DB (PostgreSQL in `.env`).
- Do not modify payment-service or product-service.
- Validate PHP with `php -l`; run `composer` scripts / phpunit where available. Do not run destructive DB operations.
- Frontend: use `lucide-react` (not `lucide.react`) and `RefreshCwIcon` (not `ArrowPathIcon`).

## Review Focus
- Notification `settings`: entity maps `setting_key`, migration creates `key` — a fixture insert on a migrated DB will fail. Pin with a fixture run or document the migration fix.
- Order `ship()` before `receive()` throws — fixture state machine must call receive first.
- Invoice/contact entities lack setters for status/number — use domain methods; reflection only if unavoidable, documented.
- Unique constraints: contact (taxNumber+type), inventory (product_id), invoice (number, activeOrderKey), notification (3 uniques), order (distinct order numbers).
- Frontend dev server on :5173 serves a separate container copy at `/proc/<vite-pid>/root/app/src/` — edits must be synced there too.

---

### Task 1: Root compose for order service (symfony-developer)
**Files:** Create `docker-compose.order.yml`; Modify `docker-compose.yml`, `.env`; Create `services/order-service/migrations/Version*.php`
- [ ] Generate the initial order migration via `php bin/console make:migration` (or hand-write portable schema builder) for tables `orders`, `order_line`, `outbox_event`.
- [ ] Create `docker-compose.order.yml` with `orders-service` (php-fpm, entrypoint runs migrations then register.sh), `order-service-web` (nginx), `orders-db` (mysql:8.4), `orders-cache-redis` (redis:7-alpine), network `microservice_net`.
- [ ] Add `docker-compose.order.yml` to `include:` in `docker-compose.yml`; declare `order_db_data`, `order_redis_data` volumes.
- [ ] Add `ORDER_DB_*` defaults to root `.env`.
- [ ] Validate: `docker compose -f docker-compose.yml config --quiet` (or `docker compose config`).

### Task 2: Verify/fix notification compose (symfony-developer)
**Files:** Modify `docker-compose.notification.yml` only if defects found
- [ ] Confirm service names, healthchecks, volumes and `microservice_net` match the include model.
- [ ] Fix Consul registration port to the HTTP port (8080) if the gateway expects HTTP.
- [ ] Validate compose config.

### Task 3: Contact fixtures (symfony-developer)
**Files:** Modify `services/contact-service/src/DataFixtures/AppFixtures.php`
- [ ] Generate 30 `Contact` rows (vary taxNumber+contactType to respect unique index), spread across CLIENT/SUPPLIER and ACTIVE/INACTIVE/BLACKLISTED via domain methods.
- [ ] Generate 30 `ContactAuditEntry` rows referencing persisted contact IDs (userId >= 1).
- [ ] `php -l` pass; flush in batches.

### Task 4: Inventory fixtures (symfony-developer)
**Files:** Modify `services/inventory-service/src/DataFixtures/InventoryFixtures.php`
- [ ] 30 `Product` rows with explicit `setId()` (no auto-increment).
- [ ] 30 `Inventory` rows with unique `productId`, createdAt/updatedAt set.
- [ ] 30 `InventoryMovement` rows via `setInventory()` (also sets productId), varied types.

### Task 5: Invoice fixtures (symfony-developer)
**Files:** Add `doctrine/doctrine-fixtures-bundle` to require-dev; Create `services/invoice-service/src/DataFixtures/AppFixtures.php`
- [ ] 30 `Invoice` rows (unique orderId→activeOrderKey, unique numbers via `issue()`), 30+ `InvoiceLine` rows via `addLine()`.
- [ ] Respect status transitions; do not force illegal states.

### Task 6: Notification fixtures (symfony-developer)
**Files:** Modify `services/notification-service/src/DataFixtures/AppFixtures.php`
- [ ] 30 rows each for: `Notification`, `Delivery`, `DeliveryAttempt`, `NotificationPreference`, `NotificationTemplate`, `ProcessedEvent`, `Setting`.
- [ ] Respect unique constraints; use domain state transitions for delivery statuses; fix/flag the `setting_key` vs `key` migration mismatch.

### Task 7: Order fixtures (symfony-developer)
**Files:** Modify `services/order-service/src/DataFixtures/V1/OrdersFixtures.php`
- [ ] 30 `Order` rows and 30+ `OrderLine` rows; fix the `ship()`-before-`receive()` bug by calling `receive()` first.
- [ ] Mix order types and statuses through valid domain transitions.

### Task 8: Frontend submenu + user display (frontend-developer)
**Files:** Modify `frontend/src/components/dashboard/nav-main.tsx`, `app-sidebar.tsx`, `nav-user.tsx`, `src/services/auth-api.tsx`; then sync to running container copy.
- [ ] Extend nav item type with optional `items: []`; render `SidebarMenuSub` + collapsible toggle; active state considers children.
- [ ] Nest `Payment plans` and `Allocations` under `Payments`.
- [ ] Add `fullName` to `AuthUser`; `NavUser` reads from `useAuth()` with initials fallback.
- [ ] Run `npm run lint` and `npm run test`; verify in browser on :5173 after syncing files to the container copy.

---
*Self-review: all spec sections mapped to tasks; unique constraints and DB drivers pinned; frontend container-sync risk captured in Review Focus.*
