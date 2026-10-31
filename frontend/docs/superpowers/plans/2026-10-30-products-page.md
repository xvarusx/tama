# Products Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dedicated Products page to the React dashboard where users can list, create, edit, and toggle products between `active` and `archive`.

**Architecture:** Follow the existing Clients feature: a `products-api` service module, page-owned state, presentational table/dialog components, a validation module, and Vitest coverage. The React app is served by Vite and proxies `/api/products` and `/api/product` to `product-service` on port 8003. The backend gets a minimal fix for its broken update endpoint, correct `422` validation responses, and a status backfill.

**Tech Stack:** React 19, TypeScript, Vite 8, React Router 7, Axios, Tailwind v4, Base UI/shadcn components, Vitest 5; Symfony 8 + Doctrine on the product-service side, PHPUnit 13.

**Spec:** `docs/superpowers/specs/2026-10-30-products-page-design.md`

## Global Constraints

- Status values are exactly `active` and `archive`. Existing rows with any other value are backfilled to `active`.
- Prices are whole numbers: `DECIMAL(10, 0)`. Never accept decimals in the form.
- `imageUrl` accepts only `https://` or `ftp://` URLs.
- `GET /api/products?page=&limit=` returns a plain array with no total; `limit` is capped at 100.
- The Products page does not show or change stock, does not delete products, and does not edit categories.
- Product requests use relative `/api/products` and `/api/product/...` paths; Vite proxies them to `http://127.0.0.1:8003`.
- The React app (`/home/oussema/Work/tries/2026-10-15-reactJs/myblog`) is **not** a git repository. Do not add commit steps there.
- The backend (`/home/oussema/Work/tries/2026-09-20-microservices-symfony/erp/services/product-service`) is a git repository.
- The PHPUnit test database resolves to `products_db_test_test` (`.env.test` already ends in `_test` and Doctrine appends `_test` again). Use it as-is.
- Validation failures must return HTTP `422` with `{ "error": "..." }`, never `200`.

## Review Focus

1. A save the backend rejects must show an error toast, never a success toast.
2. A PATCH that changes only `status` must leave every other field intact.
3. A decimal price (`12.5`) must be rejected in the form, not silently rounded.
4. A `http://` or malformed `imageUrl` must be rejected before it reaches the API.
5. An empty product list, or a page number past the end, must render the empty state and clamp the page — no infinite spinner and no crash.

---

### Task 1: Backend — working update endpoint and honest validation status codes

**Files:**
- Modify: `/home/oussema/Work/tries/2026-09-20-microservices-symfony/erp/services/product-service/src/Controller/Api/ProductsController.php`
- Modify: `/home/oussema/Work/tries/2026-09-20-microservices-symfony/erp/services/product-service/tests/Controller/Api/ProductsControllerTest.php`

**Interfaces:**
- Produces: `PUT /api/product/{id}` and `PATCH /api/product/{id}` return `200` with the updated product; a missing product returns `404`; invalid payloads return `422` with `{"error": string}`.
- Produces: `POST /api/product/` returns `201` on success and `422` on validation failure.

- [ ] **Step 1: Prepare the test database**

Run from the product-service directory:

```bash
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

Expected: the pending `Version20261029235728` migration applies (it adds `status` and drops `is_active`).

- [ ] **Step 2: Bring the stale test file onto the current API**

In `tests/Controller/Api/ProductsControllerTest.php`:

- Replace `->setIsActive(true)` with `->setStatus('active')` in `seedProduct()`.
- Replace every `'isActive' => true` request payload with `'status' => 'active'`.
- Delete `testDeleteRemovesTheProduct` and `testDeleteMissingProductReturns404` (there is no DELETE route).

Then add these four tests, keeping the file's existing helpers:

```php
public function testUpdatePersistsChangedFieldsAndStatus(): void
{
    $this->removeProducts();
    $product = $this->seedProduct('Old Name', 'Screens');
    $id = $product->getId();

    $this->client->request('PUT', '/api/product/'.$id, [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode([
        'name' => 'Renamed Product',
        'sku' => $product->getSku(),
        'unit' => 'kg',
        'sellingPrice' => '30',
        'costPrice' => '20',
        'status' => 'archive',
    ]));

    self::assertResponseStatusCodeSame(Response::HTTP_OK);
    $body = json_decode($this->client->getResponse()->getContent(), true);
    self::assertSame($id, $body['id']);
    self::assertSame('Renamed Product', $body['name']);
    self::assertSame('archive', $body['status']);

    $em = $this->em();
    $em->clear();
    $updated = $em->getRepository(Product::class)->find($id);
    self::assertSame('Renamed Product', $updated->getName());
    self::assertSame('30', $updated->getSellingPrice());
    self::assertSame('archive', $updated->getStatus());
    self::assertCount(1, $em->getRepository(Product::class)->findAll());
}

public function testPatchCanChangeOnlyStatus(): void
{
    $this->removeProducts();
    $product = $this->seedProduct('Only Status', 'Screens');
    $id = $product->getId();

    $this->client->request('PATCH', '/api/product/'.$id, [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['status' => 'archive']));

    self::assertResponseStatusCodeSame(Response::HTTP_OK);

    $em = $this->em();
    $em->clear();
    $updated = $em->getRepository(Product::class)->find($id);
    self::assertSame('archive', $updated->getStatus());
    self::assertSame('Only Status', $updated->getName());
    self::assertSame('12', $updated->getSellingPrice());
    self::assertSame('kg', $updated->getUnit());
}

public function testCreateWithInvalidPayloadReturns422(): void
{
    $this->removeProducts();

    $this->client->request('POST', '/api/product/', [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode([
        'name' => 'Bad Image',
        'sku' => 'BAD-001',
        'unit' => 'kg',
        'sellingPrice' => '20',
        'costPrice' => '15',
        'status' => 'active',
        'imageUrl' => 'not-a-url',
    ]));

    self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    $body = json_decode($this->client->getResponse()->getContent(), true);
    self::assertArrayHasKey('error', $body);
}

public function testUpdateWithInvalidPayloadReturns422(): void
{
    $this->removeProducts();
    $product = $this->seedProduct('Valid Product', 'Screens');

    $this->client->request('PUT', '/api/product/'.$product->getId(), [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode(['status' => 'draft']));

    self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    $body = json_decode($this->client->getResponse()->getContent(), true);
    self::assertArrayHasKey('error', $body);
}
```

- [ ] **Step 3: Run the suite to confirm it fails**

```bash
php bin/phpunit tests/Controller/Api/ProductsControllerTest.php
```

Expected: the update tests fail (the current update path throws `ArgumentCountError` on `setStatus()`), and the invalid-payload tests fail because validation currently returns `200`.

- [ ] **Step 4: Fix the controller**

In `ProductsController.php`:

- Add `use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;`.
- In `update()`, replace the deserialize call with:

```php
$updatedProduct = $serializer->deserialize(
    $request->getContent(),
    Product::class,
    'json',
    [AbstractNormalizer::OBJECT_TO_POPULATE => $product],
);
```

- In both `create()` and `update()`, change the validation response to:

```php
return new JsonResponse(['error' => (string) $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
```

- In `update()`, keep `$product->setUpdatedAt(new DateTimeImmutable()); $em->flush();` and dispatch `status: $product->getStatus()` (not `$updatedProduct->setStatus()`).

- [ ] **Step 5: Run the suite to confirm it passes**

```bash
php bin/phpunit tests/Controller/Api/ProductsControllerTest.php
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Controller/Api/ProductsController.php tests/Controller/Api/ProductsControllerTest.php
git commit -m "fix(product): persist updates and return 422 for validation errors"
```

---

### Task 2: Backend — backfill status and repair fixtures

**Files:**
- Create: `/home/oussema/Work/tries/2026-09-20-microservices-symfony/erp/services/product-service/migrations/Version<YYYYMMDDHHMMSS>.php`
- Modify: `/home/oussema/Work/tries/2026-09-20-microservices-symfony/erp/services/product-service/src/DataFixtures/ProductsFixtures.php`

**Interfaces:**
- Produces: every product row has `status` equal to `active` or `archive`; a fresh fixtures load creates 100 active products.

- [ ] **Step 1: Generate the migration**

```bash
php bin/console doctrine:migrations:generate
```

Rename/keep the generated class and write:

```php
public function up(Schema $schema): void
{
    $this->addSql("UPDATE product SET status = 'active' WHERE status NOT IN ('active', 'archive')");
}

public function down(Schema $schema): void
{
    // The pre-existing status values are unknown, so there is nothing to restore.
}
```

- [ ] **Step 2: Apply it to the dev and test databases**

```bash
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

Expected: the migration reports one executed statement on each database.

- [ ] **Step 3: Fix the fixtures**

In `ProductsFixtures.php`, replace the `isActive`/float lines with integer, status-based values:

```php
$cost = $randomizer->getInt(10, 150);
$product->setStatus('active');
$product->setUnit('kg');
$product->setCostPrice((string) $cost);
$product->setSellingPrice((string) round($cost * 1.24));
```

Keep the existing name/sku/description/brand/category assignments.

- [ ] **Step 4: Verify fixtures load and produce active rows**

```bash
php bin/console doctrine:fixtures:load --env=test --no-interaction
php -r '$p=new PDO("mysql:host=127.0.0.1;dbname=products_db_test_test","root",""); echo $p->query("SELECT COUNT(*) FROM product WHERE status = \"active\"")->fetchColumn(), "\n";'
```

Expected: `100`.

- [ ] **Step 5: Commit**

```bash
git add migrations src/DataFixtures/ProductsFixtures.php
git commit -m "fix(product): backfill status and repair product fixtures"
```

---

### Task 3: Frontend — products API service

**Files:**
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/services/products-api.tsx`
- Test: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/services/products-api.test.ts`
- Modify: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/vite.config.ts`

**Interfaces:**
- Produces:
  - `PRODUCTS_API_URL: string` (`import.meta.env.VITE_PRODUCTS_API_URL ?? ""`)
  - `PRODUCT_STATUS = { active: "Active", archive: "Archived" } as const`
  - `type ProductStatus = keyof typeof PRODUCT_STATUS`
  - `interface Product { id: number; sku: string; name: string; description: string | null; brand: string | null; unit: string; sellingPrice: string; costPrice: string; imageUrl: string | null; status: ProductStatus; createdAt: string; updatedAt: string; category: { id: number; name: string } | null }`
  - `interface ProductInput { sku: string; name: string; description: string | null; brand: string | null; unit: string; sellingPrice: string; costPrice: string; imageUrl: string | null; status: ProductStatus }`
  - `getProducts(page: number, limit: number): Promise<Product[]>` (default export)
  - `getProductsTotal(): Promise<number>`
  - `createProduct(input: ProductInput): Promise<Product>`
  - `updateProduct(id: number, input: ProductInput): Promise<Product>`
  - `setProductStatus(product: Product, status: ProductStatus): Promise<Product>`

- [ ] **Step 1: Write the failing tests**

Mirror `src/services/clients-api.test.ts`'s mocking style. Cover:

- `getProducts(2, 25)` calls `api.get("/api/products", { baseURL: PRODUCTS_API_URL, params: { page: 2, limit: 25 } })` and returns the array.
- `getProductsTotal()` with a 100-row page then a 25-row page resolves `125`, calling pages 1 and 2 at `limit: 100`.
- `getProductsTotal()` with a full page then an empty page resolves `100`.
- `getProductsTotal()` with an empty first page resolves `0` after one call.
- `getProductsTotal()` throws `"Unable to determine product total."` after 1,000 full pages.
- `createProduct(input)` posts to `/api/product/` with the input and returns the product.
- `updateProduct(7, input)` puts to `/api/product/7`.
- `setProductStatus(product, "archive")` puts the product's full payload with `status: "archive"` and returns the updated product.
- A 2xx response shaped `{ error: "Invalid image URL" }` from `createProduct` rejects with that message instead of resolving.

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
pnpm vitest run src/services/products-api.test.ts
```

Expected: fail — module not found.

- [ ] **Step 3: Implement the service**

Use the shared `api` instance from `@/lib/api`. For every call pass `baseURL: PRODUCTS_API_URL`. Implement the total walk exactly like `getClientsTotal()`: pages of 100, stop on a short page, cap at 1,000 pages and throw `"Unable to determine product total."`. Log and rethrow errors like `clients-api`.

For `createProduct` and `updateProduct`, run the response through a guard:

```ts
function ensureProduct(data: unknown): Product {
  if (data && typeof data === "object" && "error" in data && !("id" in data)) {
    throw new Error(String((data as { error: unknown }).error));
  }
  return data as Product;
}
```

Implement `setProductStatus` by re-sending the full payload:

```ts
export async function setProductStatus(
  product: Product,
  status: ProductStatus,
): Promise<Product> {
  return updateProduct(product.id, {
    sku: product.sku,
    name: product.name,
    description: product.description,
    brand: product.brand,
    unit: product.unit,
    sellingPrice: product.sellingPrice,
    costPrice: product.costPrice,
    imageUrl: product.imageUrl,
    status,
  })
}
```

- [ ] **Step 4: Run the tests to confirm they pass**

```bash
pnpm vitest run src/services/products-api.test.ts
```

Expected: pass.

- [ ] **Step 5: Add the Vite dev proxy**

Change `vite.config.ts` to a function config so it can read the environment:

```ts
import { defineConfig, loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const productsTarget =
    env.VITE_PRODUCTS_API_URL || 'http://127.0.0.1:8003'
  const proxy = {
    '/api/products': { target: productsTarget, changeOrigin: true },
    '/api/product': { target: productsTarget, changeOrigin: true },
  }

  return {
    // keep the existing plugins and resolve.alias
    server: { proxy },
    preview: { proxy },
  }
})
```

Keep the existing plugins and `@` alias exactly as they are.

- [ ] **Step 6: Verify the proxy configuration builds**

```bash
pnpm build
```

Expected: build succeeds.

---

### Task 4: Frontend — product form validation

**Files:**
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/lib/product-validation.ts`
- Test: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/lib/product-validation.test.ts`

**Interfaces:**
- Consumes: `Product`, `ProductInput`, `ProductStatus` from `@/services/products-api`.
- Produces:
  - `interface ProductFormValues { name: string; sku: string; brand: string; unit: string; sellingPrice: string; costPrice: string; imageUrl: string; description: string; status: ProductStatus }`
  - `type ProductFormErrors = Partial<Record<keyof ProductFormValues, string>>`
  - `createEmptyProductForm(): ProductFormValues` (status `"active"`, all strings empty)
  - `toProductFormValues(product: Product): ProductFormValues`
  - `validateProductForm(values: ProductFormValues): ProductFormErrors`
  - `toProductInput(values: ProductFormValues): ProductInput`

- [ ] **Step 1: Write the failing tests**

Cover:

- Empty form → errors on `name`, `sku`, `unit`, `sellingPrice`, `costPrice`; no error on `status`.
- `sellingPrice: "12.5"` → `"Enter a whole number."`
- `sellingPrice: "-1"` → `"Enter a whole number."`
- `costPrice: "abc"` → `"Enter a whole number."`
- `imageUrl: "http://example.com/a.png"` → `"Use an https:// or ftp:// URL."`
- `imageUrl: "https://example.com/a.png"` → no `imageUrl` error.
- `imageUrl: "not a url"` → `"Use an https:// or ftp:// URL."`
- `status: "draft" as ProductStatus` → `"Status is invalid."`
- `toProductInput` trims text and converts empty optional fields (`brand`, `imageUrl`, `description`) to `null`.
- `toProductFormValues` converts `null` fields to empty strings and keeps `status`.

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
pnpm vitest run src/lib/product-validation.test.ts
```

Expected: fail — module not found.

- [ ] **Step 3: Implement the validation module**

Rules:

- `name`, `sku`, `unit` required after trim.
- `sellingPrice` and `costPrice` required and must match `/^\d+$/`.
- `imageUrl` empty is fine; otherwise `new URL(value)` must parse and `url.protocol` must be `https:` or `ftp:`.
- `status` must be `active` or `archive`.
- `toProductInput` sends prices as trimmed strings.

- [ ] **Step 4: Run the tests to confirm they pass**

```bash
pnpm vitest run src/lib/product-validation.test.ts
```

Expected: pass.

---

### Task 5: Frontend — share the pagination control

**Files:**
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/common/table-pagination.tsx`
- Modify: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/pages/dashboard/clients/page.tsx`
- Delete: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/clients/client-pagination.tsx`

**Interfaces:**
- Produces: `TablePagination` with props `{ page: number; limit: number; total: number; onPageChange: (page: number) => void; onLimitChange: (limit: number) => void }`.

- [ ] **Step 1: Move the component**

Copy `client-pagination.tsx` to `components/common/table-pagination.tsx`, rename the component and props interface to `TablePagination`/`TablePaginationProps`, and leave the markup and behavior unchanged.

- [ ] **Step 2: Update the Clients page**

Replace the `ClientsPagination` import and usage with `TablePagination` from `@/components/common/table-pagination`. Delete `client-pagination.tsx`.

- [ ] **Step 3: Verify nothing regressed**

```bash
pnpm test
pnpm build
```

Expected: all tests pass and the production build succeeds.

---

### Task 6: Frontend — dashboard layout and Clients route

**Files:**
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/pages/dashboard/layout.tsx`
- Delete: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/pages/dashboard/page.tsx`
- Modify: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/main.tsx`
- Modify: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/dashboard/app-sidebar.tsx`
- Modify: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/dashboard/nav-main.tsx`
- Modify: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/dashboard/site-header.tsx`

**Interfaces:**
- Produces: routes `/dashboard` (redirects to `clients`), `/dashboard/clients`, and later `/dashboard/products`.
- Produces: `NavMain` items typed `{ title: string; url: string; icon: LucideIcon; disabled?: boolean }[]` rendered with React Router `Link`; disabled items render a disabled `SidebarMenuButton`.

- [ ] **Step 1: Create the layout**

Move the current dashboard shell into `src/pages/dashboard/layout.tsx`. Keep `SidebarProvider`, `SiteHeader`, `AppSidebar`, and `SidebarInset`, import `Outlet` from `react-router-dom`, replace the placeholder grid and `<ClientsPage />` with `<Outlet />` inside the padded content area, and export it as `DashboardLayout`.

- [ ] **Step 2: Rewrite the routes**

In `main.tsx`:

```tsx
<Route element={<ProtectedRoute />}>
  <Route path="/dashboard" element={<DashboardLayout />}>
    <Route index element={<Navigate to="clients" replace />} />
    <Route path="clients" element={<ClientsPage />} />
  </Route>
</Route>
```

Import `Navigate` and `DashboardLayout`. Delete `src/pages/dashboard/page.tsx`.

- [ ] **Step 3: Wire the sidebar**

In `nav-main.tsx`, import `Link` and `useLocation` from `react-router-dom`, render `render={<Link to={item.url} />}`, pass `isActive={pathname === item.url}`, and pass `disabled={item.disabled}`. Remove the sub-item rendering and the `items?` field from the type.

In `app-sidebar.tsx`, replace `navMain` with three top-level entries: Clients (`/dashboard/clients`, `SquareTerminal`), Products (`/dashboard/products`, `BookOpen`), Inventory (`#`, `Bot`, `disabled: true`, inert until its page exists). Drop the fake History/Starred/General sub-items.

- [ ] **Step 4: Make the breadcrumb truthful**

In `site-header.tsx`, derive the second crumb from `useLocation().pathname`: `/dashboard/clients` → "Clients". First crumb: "Dashboard", linking to `/dashboard`.

- [ ] **Step 5: Verify the shell**

```bash
pnpm build
```

Expected: build succeeds. Manually confirm `/dashboard` redirects to `/dashboard/clients` and the Clients link is active.

---

### Task 7: Frontend — product UI components

**Files:**
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/products/product-table.tsx`
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/products/product-form-dialog.tsx`
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/products/product-create.tsx`
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/products/product-status-dialog.tsx`

**Interfaces:**
- Consumes: everything from Tasks 3 and 4.
- Produces:
  - `type ProductsStatus = "loading" | "error" | "ready"`
  - `ProductsTable` props `{ products: Product[]; status: ProductsStatus; error: string; onRetry: () => void; onEdit: (product: Product) => void; onStatusChange: (product: Product) => void }`
  - `ProductCreate` props `{ onCreated: () => void }`
  - `ProductFormDialog` props `{ open: boolean; onOpenChange: (open: boolean) => void; product?: Product | null; onSaved: (product: Product, mode: "create" | "edit") => void }`
  - `ProductStatusDialog` props `{ product: Product; onOpenChange: (open: boolean) => void; onChanged: (product: Product) => void }`

- [ ] **Step 1: Build the table**

Mirror `client-table.tsx`. Columns: Product (thumbnail when `imageUrl` is present, name, SKU), Brand, Category, Unit, Selling, Cost, Status, Actions. Render `—` for null brand/category. Render status as `PRODUCT_STATUS[product.status] ?? product.status`. Loading/error/empty rows and a Retry button use the same structure as the Clients table. The row menu has Edit and `Archive product`/`Activate product`; no Delete.

- [ ] **Step 2: Build the form dialog**

Mirror `client-form-dialog.tsx` for state, errors, toasts, and the saving lock. Fields: name, SKU, brand, unit, selling price (`type="number"`, `min="0"`, `step="1"`), cost price (same), image URL, description, status select using `PRODUCT_STATUS`. When editing a product with a category, show a read-only line: `Category: <name> (read-only)`. On submit call `validateProductForm`, then `createProduct`/`updateProduct` via `toProductInput`, and call `onSaved(saved, mode)`.

- [ ] **Step 3: Build the create button and status dialog**

`product-create.tsx` mirrors `client-create.tsx`: an "Add Product" button that opens the form dialog and calls `onCreated()` after a successful create.

`product-status-dialog.tsx` mirrors `delete-client-dialog.tsx` with `AlertDialog`: title `Archive product`/`Activate product`, body naming the product and the resulting state, action label `Archive`/`Activate`, destructive variant for archive. It calls `setProductStatus(product, nextStatus)` and then `onChanged(updated)`.

- [ ] **Step 4: Verify the components compile and lint**

```bash
pnpm build
pnpm lint
```

Expected: both succeed.

---

### Task 8: Frontend — Products page and route

**Files:**
- Create: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/pages/dashboard/products/page.tsx`
- Modify: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/main.tsx`
- Modify: `/home/oussema/Work/tries/2026-10-15-reactJs/myblog/src/components/dashboard/site-header.tsx`

**Interfaces:**
- Consumes: `getProducts`, `getProductsTotal`, `Product`; `TablePagination`; the four product components from Task 7.

- [ ] **Step 1: Build the page**

Mirror `src/pages/dashboard/clients/page.tsx`:

- State: `page` (1), `limit` (10), `products`, `total`, `status` (`"loading"`), `error`, `reloadKey`, `editingProduct`, `statusTarget`.
- Effect keyed on `[page, limit, reloadKey]`: `Promise.all([getProducts(page, limit), getProductsTotal()])`, clamp to the last page when `page > totalPages`, otherwise set rows/total/status.
- `refresh()`, `changePage()`, `changeLimit()`, `handleCreated()` (page 1 + refresh), `handleSaved()` (refresh), `handleStatusChanged()` (refresh) follow the Clients page.
- Render `ProductCreate`, `ProductsTable`, and `TablePagination`, then the edit dialog and status dialog conditionally.

- [ ] **Step 2: Add the route and breadcrumb label**

In `main.tsx`, add `<Route path="products" element={<ProductsPage />} />` under the dashboard layout. In `site-header.tsx`, map `/dashboard/products` → "Products".

- [ ] **Step 3: Verify**

```bash
pnpm test
pnpm lint
pnpm build
```

Expected: all pass.

---

### Task 9: End-to-end verification

**Files:** none (verification only).

- [ ] **Step 1: Run the backend suite**

```bash
cd /home/oussema/Work/tries/2026-09-20-microservices-symfony/erp/services/product-service
php bin/phpunit tests/Controller/Api/ProductsControllerTest.php
```

Expected: pass.

- [ ] **Step 2: Start product-service on 8003**

```bash
cd /home/oussema/Work/tries/2026-09-20-microservices-symfony/erp/services/product-service
symfony serve --port=8003 -d
curl -sS "http://127.0.0.1:8003/api/products?page=1&limit=1" | head -c 400
```

Expected: a JSON array with one product whose `status` is `active` or `archive`.

- [ ] **Step 3: Run the frontend**

```bash
cd /home/oussema/Work/tries/2026-10-15-reactJs/myblog
pnpm dev
```

Log in, open `/dashboard/products`, and confirm:

- The list loads with product names, SKUs, prices, and status badges.
- Creating a product shows a success toast and the product appears after refresh.
- Editing a product changes the row after refresh.
- Archiving and activating both flip the badge after refresh.
- Submitting a decimal price or an `http://` image URL shows a field error and no request is sent.
- A backend rejection (if forced) shows an error toast, not a success toast.

- [ ] **Step 4: Confirm the empty and out-of-range states**

With the list on a later page, delete products directly in the database until the current page no longer exists, refresh, and confirm the page clamps to the last page instead of spinning. Confirm an empty result renders "No products yet."

---

## Self-Review Notes

- Spec coverage: backend fix + 422 (Task 1), backfill + fixtures (Task 2), service + dev proxy (Task 3), validation (Task 4), shared pagination (Task 5), routing/shell (Task 6), UI (Task 7), page + route (Task 8), verification (Task 9).
- Type consistency: `Product`, `ProductInput`, `ProductStatus`, `ProductFormValues`, `ProductsStatus`, and all component props are defined once in their task's Interfaces block and reused by name.
- Review Focus coverage: items 1 and 2 are pinned by Task 1 tests and the Task 3 guard test; items 3 and 4 by Task 4 tests; item 5 by the Task 8 clamp logic and Task 9 manual checks.
- The React app has no git repository, so only backend tasks carry commit steps.
