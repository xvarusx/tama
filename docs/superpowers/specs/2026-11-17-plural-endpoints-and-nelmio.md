# Pluralize service endpoints + complete Nelmio docs (2026-11-17)

## Goal
1. Rename all singular service endpoints to plural — backend routes, frontend API bases,
   gateway locations/upstreams, and docker-compose files/service names. **Authentication
   service is EXCLUDED from pluralization.**
2. Ensure every backend service has working Nelmio API documentation (backend only).

## Canonical singular → plural mapping (apply EVERYWHERE)
```
contact        -> contacts
inventory      -> inventories
invoice        -> invoices
payment        -> payments
payment-plan   -> payment-plans
installment    -> installments
reminder       -> reminders
allocation     -> allocations
setting        -> settings
product        -> products
notification   -> notifications
order          -> orders
```
Authentication (`authentication-service` / `/api/authenticator/`) is unchanged.

## Layer 1 — Backend Symfony routes
- Most services already use plural class-level route prefixes (`/contacts`, `/invoices`,
  `/payments`, `/payment-plans`, `/installments`, `/allocations`, `/reminders`, `/settings`,
  `/products`, `/notifications`, `/orders`). Do NOT change these.
- **inventory-service ONLY**: `InventoryController` currently has `/inventory...` method
  routes → change to `/inventories...` so all paths become `/api/v1/inventories`,
  `/api/v1/inventories/low-stock`, `/api/v1/inventories/product/{id}`,
  `/api/v1/inventories/{id}/movements`, etc. Update any tests referencing the old paths.

## Layer 2 — Frontend
Mechanical prefix pluralization (keep resource paths as-is, so e.g. `/api/contact/contacts`
becomes `/api/contacts/contacts`; gateway rewrites drop the prefix generically):
- `contacts-api.tsx`: `/api/contact/` → `/api/contacts/` (all inline URLs + tests)
- `inventory-api.tsx`: BASE `/api/inventory` → `/api/inventories`
- `invoices-api.tsx`: BASE `/api/invoice` → `/api/invoices`
- `payments-api.tsx`: `/api/payment`→`/api/payments`, `/api/payment-plan`→`/api/payment-plans`,
  `/api/installment`→`/api/installments`, `/api/allocation`→`/api/allocations`,
  `/api/reminder`→`/api/reminders`, `/api/setting`→`/api/settings`
- `products-api.tsx`: BASE `/api/product` → `/api/products`
- `notifications-api.tsx`: BASE `/api/notification` → `/api/notifications`
- update the corresponding `*.test.ts` expectations
- `vite.config.ts`: proxy key `/api/product` → `/api/products` and rewrite regex
  `/^\/api\/product/` → `/^\/api\/products/`

## Layer 3 — API gateway (`infrastructure/gateway/nginx.conf`)
Keep the inventory-style no-trailing-slash locations (avoids port-dropping 301s):
```
location /api/contacts       rewrite ^/api/contacts(/.*)?$      /api/v1$1          (generic)
location /api/inventories    rewrite ^/api/inventories(/.*)?$   /api/v1/inventories$1
location /api/invoices       rewrite ^/api/invoices(/.*)?$      /api/v1/invoices$1
location /api/payments       rewrite ^/api/payments(/.*)?$      /api/v1/payments$1
location /api/payment-plans  rewrite ^/api/payment-plans(/.*)?$ /api/v1/payment-plans$1
location /api/installments   rewrite ^/api/installments(/.*)?$  /api/v1/installments$1
location /api/reminders      rewrite ^/api/reminders(/.*)?$     /api/v1/reminders$1
location /api/allocations    rewrite ^/api/allocations(/.*)?$   /api/v1/allocations$1
location /api/settings       rewrite ^/api/settings(/.*)?$      /api/v1/settings$1
location /api/products       rewrite ^/api/products(/.*)?$      /api/v1$1          (generic)
location /api/notifications  rewrite ^/api/notifications(/.*)?$ /api/v1$1          (generic)
```
Upstream hostnames stay SINGULAR (docker-compose is unchanged):
`contact-service-web:80`, `inventory-service-web:80`, `invoice-service-web:80`,
`payment-service-web:80`, `product-service-web:8080`, `notification-service-web:8080`.
Auth locations and `/health`, `/` unchanged.

## Layer 4 — docker-compose files: NO CHANGES (decision 2026-11-17)
All docker-compose files, service keys, container names and aliases stay EXACTLY as-is.
Only HTTP paths change. Consequently the gateway `$upstream` hostnames MUST remain the
existing singular container names: `contact-service-web:80`, `inventory-service-web:80`,
`invoice-service-web:80`, `payment-service-web:80`, `product-service-web:8080`,
`notification-service-web:8080`. Do not rename them.

## Layer 5 — Nelmio docs (backend only)
Target state: every backend service renders Swagger UI and has OA annotations on controllers.
- **inventory-service**: bundle NOT in bundles.php, no `config/packages/nelmio_api_doc.yaml`,
  no swagger route. Add all three; wire `nelmio_api_doc.yaml` route. Controllers already have OA.
- **contact-service**: `ContactStatusController` has NO OA annotations — add them; also add
  missing `config/packages/nelmio_api_doc.yaml` (swagger route file already exists).
- **authentication-service**: (DECISION: document it too) no Nelmio at all — add
  `nelmio/api-doc-bundle` dependency, register bundle, add config + swagger route, and
  annotate all 3 controllers. Keep it reachable only on the Docker network (gateway does
  not need a public docs route; if one is added, scope it so the internal user endpoint
  stays private). This service is EXCLUDED from pluralization only.
- invoice/order/payment/product/notification are already fully documented — leave them.

## Non-goals
- Do not change authentication endpoint paths or its compose file name.
- Do not modify frontend beyond API base strings, tests, and vite proxy.
- Do not change any business logic.

## Verification
- `docker compose -f docker-compose.yml config --quiet` passes; plural service names present.
- Each service: `php bin/console debug:router` shows expected plural paths.
- Swagger UI reachable per service (dev), at least routes registered (`/api/doc`).
- Frontend: lint + tests pass.
- End-to-end: all previously-working API endpoints still return 200 under new plural paths.
