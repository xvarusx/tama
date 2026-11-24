# Design: Compose files, 25+ row fixtures, and Frontend navigation fixes

**Date:** 2026-11-16
**Status:** APPROVED

## Intent
Three independent improvements requested together:
1. Root-level docker-compose coverage for order and notification services.
2. Realistic seed data (more than 25 rows per table) for contact, inventory, invoice, notification, and order services.
3. Frontend: nest Payment plans + Allocations under a Payments submenu, and show the real logged-in user's name/email beside the user icon.

## Decisions (confirmed with partner)
- Fixture scope: **invoice only** (the duplicate "invoice" in the request was a typo). No payment/product fixtures.
- Order-service DB: **MySQL 8.4** (platform convention; pdo_mysql already installed).
- Approach: verify+fix the existing notification compose; create a new order compose.

## Part 1 — Docker Compose
- `docker-compose.order.yml` (new): `orders-service` (PHP-FPM, entrypoint runs migrations), `order-service-web` (nginx), `orders-db` (mysql:8.4), `orders-cache-redis` (redis:7-alpine). Network `microservice_net`. Volume `order_db_data`, `order_redis_data`. `DATABASE_URL=mysql://...`.
- `docker-compose.notification.yml`: already complete — verify and fix only defects (Consul port should point to the HTTP service 8080, keep `settings` schema consistent).
- `docker-compose.yml`: add `docker-compose.order.yml` to `include:` and declare the new volumes. Root `.env`: add `ORDER_DB_*` defaults.
- Order-service needs its first migration generated (none exist) so the entrypoint can create tables.

## Part 2 — Fixtures (30 rows per table)
- **contact-service**: `contacts` (unique taxNumber+type → vary), `contact_audit_entries`. Use `AppFixtures.php`.
- **inventory-service**: `product` (explicit setId), `inventory` (unique product_id), `inventory_movement`. Update `InventoryFixtures.php`.
- **invoice-service**: `invoices`, `invoice_lines`; optionally `credit_notes`, `invoice_payments`. Must add `doctrine/doctrine-fixtures-bundle` to require-dev (composer require --dev). PostgreSQL. Use domain methods (issue/applyPayment) or reflection for setters that don't exist.
- **notification-service**: 7 tables — `notifications`, `deliveries`, `delivery_attempts`, `notification_preferences`, `notification_templates`, `processed_events`, `settings`. Respect unique constraints and the `setting_key` vs `key` column discrepancy.
- **order-service**: `orders`, `order_line`, plus `outbox_event` produced by domain transitions. Fix the existing `ship()`-before-`receive()` bug.

Fixture conventions: use Symfony Uid `Uuid::v7()`, deterministic loops, flush in batches, respect all unique constraints, no manual SQL.

## Part 3 — Frontend
- `nav-main.tsx`: extend item type with optional `items` (children) and render `SidebarMenuSub`/`SidebarMenuSubButton` with a collapsible toggle. Active state must consider child paths.
- `app-sidebar.tsx`: move `Payment plans` and `Allocations` under `Payments` as submenu items.
- `auth-api.tsx`: add `fullName` to `AuthUser` (the `/me` endpoint already returns it).
- `nav-user.tsx`: read user from `useAuth()` instead of accepting hardcoded props; fall back to email initials; derive avatar initials from fullName.
- Sync all frontend edits into the running dev-server container copy at `/proc/<vite-pid>/root/app/src/` (port 5173 serves that separate copy).

## Non-goals
- No payment-service or product-service fixtures.
- No frontend changes beyond the sidebar submenu and user display.

## Risks
- Invoice and order services use PostgreSQL in `.env`; compose overrides will set MySQL URLs to match the platform.
- Several entities have no setters for status/number — use domain methods or reflection (documented per fixture).
- Notification `settings` migration column mismatch must be corrected or fixtures will fail on a migrated DB.
