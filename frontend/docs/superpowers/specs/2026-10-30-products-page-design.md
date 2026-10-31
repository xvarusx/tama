# Products Page — Design

## Intent

The dashboard has a Clients page but no way to manage the product catalog. Add a
dedicated Products page to the React app where a user can browse products,
add a product, edit a product, and move a product between `active` and `archive`.
Stock is explicitly out of scope: it will live on a separate Inventory page later.

## Goals

- Paginated product list backed by `product-service`, showing name, SKU, brand,
  category, unit, selling price, cost price, and status.
- Add Product dialog and Edit Product dialog, both writing through the existing
  product API.
- Toggle a product's status between `active` and `archive` from the row action
  menu, with a confirmation step.
- Loading, empty, error, and retry states; toast feedback on every mutation.
- Real routes: `/dashboard/clients` and `/dashboard/products`, with the sidebar
  links working. Today the dashboard hard-renders the Clients page.
- Vitest coverage for product validation and the products service layer.

## Non-goals

- Any inventory or stock display, stock movements, or stock actions.
- Deleting products (the current API has no delete endpoint, and the user asked
  for add/edit only).
- Creating or picking categories (no categories endpoint exists).
- Search, sorting, filtering, bulk actions, or optimistic updates.
- Image upload. `imageUrl` is a text field; the table renders a thumbnail when
  the URL loads.
- A redesigned dashboard shell.

## Anti-goals

- A page that silently fails to save because the backend update endpoint is
  broken.
- Duplicating the Clients page logic wholesale where a small shared extraction
  is obvious (pagination control).
- Leaving dead sidebar links or a static breadcrumb that lies about the page.

## Constraints

- `GET /api/products?page=&limit=` returns a plain array with no total count.
  `limit` is capped at 100 and rows are ordered by `name ASC`. The total is
  derived by walking pages at `limit = 100`, same as clients.
- Product fields come back as: `id, sku, name, description, brand, unit,
  sellingPrice, costPrice, imageUrl, status, createdAt, updatedAt, category
  { id, name } | null`. Prices are `DECIMAL(10, 0)` and serialize as strings.
- Status values are exactly `active` and `archive` (`Product::STATUS`). The
  migration that replaced `is_active` did not backfill, so all 100 existing rows
  currently have `status = ''`. They will be backfilled to `active`, and
  `ProductsFixtures` will be fixed to seed `status` instead of `isActive`.
- `imageUrl`, when present, must be a URL with an `https` or `ftp` protocol.
- Prices are stored as whole numbers. MySQL rounds decimals silently (`12.5` →
  `13`), so the form accepts and validates integers only.
- `POST /api/product/` deserializes a full `Product`; omitted `status` breaks the
  NOT NULL column, so the form always sends it.
- **Validation failures currently return HTTP 200.** `create` and `update` build
  `new JsonResponse(['error' => ..., 400])`, which puts `400` inside the body
  instead of setting the status, so Axios resolves it as success. Both will
  return `422`.
- **The update endpoint is broken.** `PUT/PATCH /api/product/{id}` deserializes
  into a throwaway `Product`, never merges it into the managed entity, and calls
  `$updatedProduct->setStatus()` with no argument (a guaranteed 500). Editing and
  status toggling require fixing this method.
- **The product-service test file is stale.** `tests/Controller/Api/ProductsControllerTest.php`
  still calls `setIsActive()` and expects a DELETE route, neither of which exist
  in the current entity/controller. The suite cannot boot as-is.
- **product-service has no CORS configuration** and no `nelmio/cors-bundle`.
  Client-service has CORS; product-service does not, so direct browser calls
  from Vite would be blocked. The gateway also does not route `/api/products`.
- There is no categories endpoint, so category is display-only in the table.

## Approaches

- **A. Mirror the clients feature (chosen).** New `products-api` service module,
  page-owned state, table, dialogs, shared pagination control, validation module,
  tests, plus the minimal backend fix and real nested routes. Consistent with the
  codebase, lowest risk, and keeps all changes close to the existing pattern.
- **B. Shared generic CRUD toolkit.** Extract reusable table/dialog/pagination
  machinery and drive both clients and products from it. Rejected: it refactors
  a working page before there is a second real consumer, which is premature.
- **C. TanStack Table.** Already a dependency, but with no server-side total,
  sorting and filtering would only apply to the current page, which misleads.
  Rejected for now; can be revisited when the API supports it.

## Parts

### 1. Backend fix (`product-service`)

- In `ProductsController::update`, deserialize into the managed entity with
  `AbstractNormalizer::OBJECT_TO_POPULATE => $product`, validate it, set
  `updatedAt`, flush, and dispatch `ProductUpdated` using `$product->getStatus()`.
  This also gives the endpoint correct partial-update behavior for PATCH.
- Return `422` (not `200`) for validation failures in both `create` and `update`.
- Add a migration that backfills `status = 'active'` where the value is not
  `active` or `archive`, so existing products are active by default.
- Fix `ProductsFixtures` to call `setStatus('active')`, use integer prices, and
  stop referencing `isActive`.
- Update `ProductsControllerTest` to the current API: `setStatus('active')`
  instead of `setIsActive`, `status` instead of `isActive` in payloads, remove
  the DELETE tests, and add tests for (a) a PUT changing fields and status,
  (b) a status-only PATCH flipping `active` → `archive`, and (c) validation
  failures returning `422`.
- Run the suite against a disposable `products_test` database so the dev database
  is not touched.

### 2. Routing and shell

- Turn the current `src/pages/dashboard/page.tsx` into a layout
  (`src/pages/dashboard/layout.tsx`) that renders `SiteHeader`, `AppSidebar`, and
  an `<Outlet />`.
- `main.tsx`: `/dashboard` becomes a layout route with an index redirect to
  `clients`, plus child routes `clients` and `products`.
- Sidebar: Clients and Products become router links to their pages; the
  placeholder sub-items (History, Starred, …) are removed. Inventory stays in the
  sidebar as an inert item until its page exists.
- Site header breadcrumb derives from the current route instead of the static
  "Data Fetching" text.
- The three empty aspect-video placeholders in the dashboard shell are removed;
  they were scaffolding and would appear above every page.

### 3. Service (`src/services/products-api.tsx`)

- `Product` and `ProductInput` types matching the API.
- `PRODUCT_STATUS = { active: "Active", archive: "Archived" }`.
- `getProducts(page, limit)`, `getProductsTotal()` (walk at 100, cap at 1,000
  pages, throw a clear error past that).
- `createProduct(input)`, `updateProduct(id, input)` (PUT full payload),
  `setProductStatus(product, status)` implemented as an `updateProduct` call with
  the flipped status.
- Requests use relative `/api/products` and `/api/product/...` paths so the same
  code works through the Vite dev proxy today and the gateway later.
- As a safety net for the backend's old behavior, a 2xx response whose body has
  an `error` and no `id` is treated as a failure and thrown.
- Errors are logged and rethrown, mirroring `clients-api`.

### 4. Dev proxy (`vite.config.ts`)

- Proxy `/api/products` and `/api/product` to `VITE_PRODUCTS_API_URL`, default
  `http://127.0.0.1:8003`, with `changeOrigin: true`. Add the same under
  `preview.proxy`. This avoids CORS without changing the backend.
- Document that local development needs product-service on port 8003; production
  needs a gateway route for `/api/products` (out of scope).

### 5. Validation (`src/lib/product-validation.ts`)

- `ProductFormValues` / `ProductFormErrors`, `createEmptyProductForm()`,
  `toProductFormValues(product)`, `validateProductForm(values)`,
  `toProductInput(values)`.
- Required: `name`, `sku`, `unit`, `sellingPrice`, `costPrice`, `status`.
- Prices must be whole numbers (`>= 0`, integer only) to match the column;
  `imageUrl` must parse as `https://` or `ftp://` when filled; `status` must be
  `active` or `archive`.
- Optional text fields trim to `null` when empty.

### 6. UI components (`src/components/products/`)

- `product-create.tsx`: "Add Product" button + dialog, mirrors `client-create`.
- `product-table.tsx`: columns Product (thumbnail, name, SKU), Brand, Category,
  Unit, Selling, Cost, Status badge, Actions. Loading/error/empty rows and a
  retry button, mirroring `client-table`.
- `product-form-dialog.tsx`: create/edit form with name, SKU, brand, unit,
  selling price, cost price, image URL, description, and status. Shows a
  read-only category note. Submits with the same toast/error behavior as
  `client-form-dialog`.
- `product-status-dialog.tsx`: confirmation `AlertDialog` for archive/activate,
  showing the target product name and the resulting state.
- Row menu: Edit, then Archive/Activate (destructive variant for archive). No
  Delete item.

### 7. Shared pagination

- Extract `src/components/clients/client-pagination.tsx` into
  `src/components/common/table-pagination.tsx` with the same props and markup,
  and update the Clients page import. Products uses it too. This is the only
  change to existing Clients code.

### 8. Page (`src/pages/dashboard/products/page.tsx`)

- Page-owned state for `page`, `limit`, rows, `total`, `status`, `error`,
  `reloadKey`, `editingProduct`, and `statusTarget`.
- Fetch rows and total whenever `page`, `limit`, or `reloadKey` changes; clamp
  the page when it exceeds the last page.
- After create: go to page 1 and refresh. After edit or status change: refresh
  the current page. Same refresh semantics as the Clients page.
- Mutations show success/error toasts.

### 9. Tests

- `src/lib/product-validation.test.ts`: required fields, price validation,
  URL validation, status validation, trim-to-null conversion.
- `src/services/products-api.test.ts`: total page walking (including the cap),
  create, update, status toggle, and the request URLs/payloads.
- No component tests: the Clients feature has none, so this stays consistent.

## Decisions left to the builder

- Exact table column widths and thumbnail fallback styling.
- Whether the price cells show raw API strings or locale-formatted numbers, as
  long as both prices are visible.
- Error copy wording, following the Clients page tone.

## Verification

- `pnpm test`, `pnpm lint`, and `pnpm build` pass.
- With product-service running on 8003 and `pnpm dev`, the Products page lists
  seeded products, creates one, edits one, and flips status both ways; each
  change survives a refresh.
- Existing products show `Active` after the backfill migration.
- Product-service PHPUnit suite passes against the disposable test database.
- A manual `PUT /api/product/{id}` with a status change returns 200 and the
  persisted row reflects the new status.
- A manual `POST /api/product/` with an invalid payload returns `422` with an
  `error` body.
