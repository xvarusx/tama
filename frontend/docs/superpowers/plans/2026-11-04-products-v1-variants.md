# Products v1 + Multi-Variant Create — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the product service healthy and seeded, then rebuild the dashboard Products page against its real `/api/v1` contract, with a create form that starts with one variant and can append more.

**Architecture:** Backend first: add the missing `ApiException` listener, pin multi-variant creation with HTTP tests, replace the broken fixtures with ordered catalog fixtures, reset the local dev DB and load them. Frontend second: rewrite the products service module to the envelope/nested/variant contract, rewrite form validation around a `variants[]` array, then the form (repeatable variant fieldset), table, page, and delete flow using shadcn components.

**Tech Stack:** Symfony 8.1 / Doctrine ORM 3 / PHPUnit 13 (product-service), React 19 / Vite 8 / TypeScript 6 / Vitest 5 / Base UI + shadcn (frontend), nginx gateway.

**Spec:** This plan; older specs in `frontend/docs/superpowers/specs/` describe the retired flat API and are superseded.

## Global Constraints

- Product API base is `/api/v1` inside the service; the browser calls `/api/product/*`, and the gateway rewrites it.
- List response: `{ status: "success", data: [...], pagination: { currentPage, totalPages, pageSize, totalItems, links } }`.
- Product shape: `{ id, type: "product", attributes: { name, description, brand: {id,name}, unit: {id,code}, category: {id,name}|null, variants: [{id, sku, barcode, options, sellingPrice, costPrice}], createdAt, updatedAt } }`.
- Create payload: `{ name, brandId, unitId, categoryId?, description?, variants: [{ sku, costPrice, sellingPrice, barcode? }] }`; at least one variant.
- Update (`PUT /products/{id}`) accepts product fields only; variants are never sent.
- Prices are decimal strings with at most 2 decimals (`/^\d{1,10}(\.\d{1,2})?$/`).
- No `status`, `imageUrl`, or product-level `sku`/`price`; the old archive/activate flow is removed.
- Commands: backend `php vendor/bin/phpunit`; frontend `pnpm test`, `pnpm build`, `pnpm lint`.

## Review Focus

- Two variants sharing a SKU (case-insensitive, trimmed) must fail client-side and surface the server `409 SKU_ALREADY_EXISTS` if bypassed.
- Prices such as `12`, `12.5`, `12.50` are valid; `12.555`, `-1`, `abc` are not.
- Empty optional fields (`description`, `categoryId`, `barcode`) must serialize to `null`, not `""`.
- Deleting the last row on a page must move to the previous page, never show an empty stale page.
- Reference endpoints (brands/units/categories) failing must show a retryable error, not an unusable blank form.

---

### Task 1: Map API exceptions to their HTTP status

**Files:**
- Create: `services/product-service/src/EventListener/ApiExceptionListener.php`
- Test: `services/product-service/tests/Controller/Api/V1/ProductsControllerTest.php`

**Interfaces:**
- Consumes: `App\Exceptions\ApiException::{getErrorCode(): string, getHttpStatus(): int}`.
- Produces: error body `{ "status": "error", "error": { "code": string, "message": string } }`.

- [ ] **Step 1: Write the failing tests**

Add to `ProductsControllerTest`:

```php
public function testCreateWithUnknownBrandReturns422(): void
{
    $unit = $this->seedUnit('pcs');

    $this->client->request('POST', '/api/v1/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
        'name' => 'Ghost Product',
        'brandId' => 999999,
        'unitId' => $unit->getId(),
        'variants' => [['sku' => 'GHOST-1', 'costPrice' => '1.00', 'sellingPrice' => '2.00']],
    ]));

    self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    $body = json_decode($this->client->getResponse()->getContent(), true);
    self::assertSame('error', $body['status']);
    self::assertSame('REFERENCE_NOT_FOUND', $body['error']['code']);
}

public function testCreateWithDuplicateSkuReturns409(): void
{
    $this->seedProduct('Existing'); // seeds variant SKU-001
    $brand = $this->seedBrand('Second Brand');
    $unit = $this->seedUnit('pcs');

    $this->client->request('POST', '/api/v1/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
        'name' => 'Clashing Product',
        'brandId' => $brand->getId(),
        'unitId' => $unit->getId(),
        'variants' => [['sku' => 'SKU-001', 'costPrice' => '1.00', 'sellingPrice' => '2.00']],
    ]));

    self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
    $body = json_decode($this->client->getResponse()->getContent(), true);
    self::assertSame('SKU_ALREADY_EXISTS', $body['error']['code']);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd services/product-service && php vendor/bin/phpunit --filter 'testCreateWithUnknownBrandReturns422|testCreateWithDuplicateSkuReturns409'`
Expected: both fail with status 500, not 422/409.

- [ ] **Step 3: Implement the listener**

```php
final class ApiExceptionListener
{
    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof ApiException) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'status' => 'error',
            'error' => ['code' => $exception->getErrorCode(), 'message' => $exception->getMessage()],
        ], $exception->getHttpStatus()));
    }
}
```

- [ ] **Step 4: Run the full backend suite**

Run: `cd services/product-service && php vendor/bin/phpunit`
Expected: all tests pass (8 tests).

- [ ] **Step 5: Commit**

```bash
git add services/product-service/src/EventListener/ApiExceptionListener.php services/product-service/tests/Controller/Api/V1/ProductsControllerTest.php
git commit -m "fix(product): map ApiException to its HTTP status"
```

---

### Task 2: Pin multi-variant creation

**Files:**
- Test: `services/product-service/tests/Controller/Api/V1/ProductsControllerTest.php`

**Interfaces:**
- Consumes: `POST /api/v1/products` with `variants[]`.
- Produces: response with one variant resource per submitted variant, in order.

- [ ] **Step 1: Write the regression test**

```php
public function testCreateProductWithMultipleVariants(): void
{
    $brand = $this->seedBrand('Variant Brand');
    $unit = $this->seedUnit('pcs');

    $this->client->request('POST', '/api/v1/products', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
        'name' => 'T-Shirt',
        'brandId' => $brand->getId(),
        'unitId' => $unit->getId(),
        'variants' => [
            ['sku' => 'TSHIRT-S', 'costPrice' => '8.00', 'sellingPrice' => '15.00', 'barcode' => '111'],
            ['sku' => 'TSHIRT-M', 'costPrice' => '8.00', 'sellingPrice' => '15.00'],
            ['sku' => 'TSHIRT-L', 'costPrice' => '9.50', 'sellingPrice' => '17.50'],
        ],
    ]));

    self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    $body = json_decode($this->client->getResponse()->getContent(), true);
    $variants = $body['data']['attributes']['variants'];

    self::assertCount(3, $variants);
    self::assertSame(['TSHIRT-S', 'TSHIRT-M', 'TSHIRT-L'], array_column($variants, 'sku'));
    self::assertSame('17.50', $variants[2]['sellingPrice']);
}
```

- [ ] **Step 2: Run it**

Run: `cd services/product-service && php vendor/bin/phpunit --filter testCreateProductWithMultipleVariants`
Expected: PASS (creation already loops over variants; this pins the behavior the UI depends on).

- [ ] **Step 3: Commit**

```bash
git add services/product-service/tests/Controller/Api/V1/ProductsControllerTest.php
git commit -m "test(product): pin multi-variant product creation"
```

---

### Task 3: Replace broken fixtures with an ordered catalog

**Files:**
- Modify: `services/product-service/src/DataFixtures/V1/CategoryFixtures.php`
- Create: `services/product-service/src/DataFixtures/V1/BrandFixtures.php`
- Create: `services/product-service/src/DataFixtures/V1/UnitFixtures.php`
- Modify: `services/product-service/src/DataFixtures/V1/ProductsFixtures.php`
- Test: `services/product-service/tests/DataFixtures/CatalogFixturesTest.php`

**Interfaces:**
- Consumes: entity constructors `new Brand(string $name, string $slug)`, `new Unit(string $code, bool $allowDecimal)`, `new Category(string $name, string $slug, int $position)`, `new Product(string $name, Brand $brand, Unit $unit)`, `new ProductVariant(string $sku, string $costPrice, string $sellingPrice, ?string $barcode = null, ?array $options = null)`, `Product::addProductVariant()`.
- Produces: fixture references `brand-acme|northwind|globex`, `unit-pcs|kg|box`, `category-monitors|keyboards|accessories`; at least one product with 2+ variants.

- [ ] **Step 1: Write the failing test**

```php
final class CatalogFixturesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testFixturesLoadCatalogWithMultipleVariants(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('doctrine:fixtures:load'));
        $tester->execute(['--no-interaction' => true]);
        $tester->assertCommandIsSuccessful();

        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertSame(3, $em->getRepository(Brand::class)->count([]));
        self::assertSame(3, $em->getRepository(Unit::class)->count([]));
        self::assertSame(3, $em->getRepository(Category::class)->count([]));
        self::assertSame(8, $em->getRepository(Product::class)->count([]));

        $multiVariant = $em->createQueryBuilder()
            ->select('p.id')
            ->from(Product::class, 'p')
            ->join('p.productVariant', 'v')
            ->groupBy('p.id')
            ->having('COUNT(v.id) > 1')
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($multiVariant);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd services/product-service && php vendor/bin/phpunit --filter CatalogFixturesTest`
Expected: FAIL with `NOT NULL constraint failed: category.slug` (or brand.slug).

- [ ] **Step 3: Implement the fixtures**

`CategoryFixtures` — pass slugs, register references:

```php
foreach ([['monitors', 'Monitors', 1], ['keyboards', 'Keyboards', 2], ['accessories', 'Accessories', 3]] as [$slug, $name, $position]) {
    $category = new Category($name, $slug, $position);
    $manager->persist($category);
    $this->addReference('category-'.$slug, $category);
}
```

`BrandFixtures` — `Acme/acme`, `Northwind/northwind`, `Globex/globex`, same reference pattern.
`UnitFixtures` — `pcs(no decimals)`, `kg(decimals)`, `box(no decimals)`.

`ProductsFixtures`:

```php
public function getDependencies(): array
{
    return [BrandFixtures::class, UnitFixtures::class, CategoryFixtures::class];
}
```

Create 8 products using `getReference()`, for example:
- 27" 4K Monitor (Acme, pcs, monitors): `MON-27` / `MON-27-PRO`
- Mechanical Keyboard (Northwind, pcs, keyboards): `KEY-87` / `KEY-104`
- Wireless Mouse (Globex, pcs, accessories): `MOU-01`
- USB-C Cable (Acme, pcs, accessories): `CAB-01`
- Monitor Stand (Northwind, box, accessories): `STD-01`
- Laptop Sleeve (Globex, pcs, accessories): `SLE-01`
- Desk Mat (Acme, pcs, accessories): `MAT-01`
- Webcam (Northwind, pcs, accessories): `CAM-01` / `CAM-4K`

Each variant gets `options` only where meaningful (`['size' => '27"']`, `['switch' => 'brown']`, ...). Prices are decimal strings.

- [ ] **Step 4: Run the fixtures test**

Run: `cd services/product-service && php vendor/bin/phpunit --filter CatalogFixturesTest`
Expected: PASS.

- [ ] **Step 5: Run the full suite and commit**

```bash
cd services/product-service && php vendor/bin/phpunit
git add services/product-service/src/DataFixtures services/product-service/tests/DataFixtures
git commit -m "fix(product): seed a valid multi-variant catalog"
```

---

### Task 4: Reset the local product database and load fixtures

Operational step for the Docker dev stack; no source change.

- [ ] **Step 1: Rebuild the service image (new code + migrations + fixtures)**

```bash
docker compose build products-service api-gateway
```

- [ ] **Step 2: Recreate the products database (destructive: drops the local catalog)**

```bash
docker compose exec -T products-db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS products_db; CREATE DATABASE products_db;"'
```

- [ ] **Step 3: Restart and confirm migrations succeed**

```bash
docker compose up -d --force-recreate products-service
docker compose logs --tail 20 products-service
```

Expected: migration `Version20261104000000` runs and the container stays up (no `category already exists`).

- [ ] **Step 4: Load fixtures**

```bash
docker compose exec -T products-service php bin/console doctrine:fixtures:load --no-interaction
```

Expected: `> loading App\DataFixtures\V1\BrandFixtures` … ends without error.

- [ ] **Step 5: Confirm the service directly**

```bash
docker compose exec -T api-gateway wget -qO- "http://product-service-web/api/v1/products?page=1&pageSize=3"
```

Expected: `{"status":"success","data":[...3 products...],"pagination":{...}}`. (The public gateway rewrite lands in Task 8.)

---

### Task 5: Rewrite the products API module

**Files:**
- Modify: `frontend/src/services/products-api.tsx`
- Modify: `frontend/src/services/products-api.test.ts`
- Modify: `frontend/src/lib/api.tsx`
- Test: `frontend/src/lib/api.test.ts`

**Interfaces:**
- Produces: `Product`, `ProductVariant`, `ProductBrand`, `ProductUnit`, `ProductCategory`, `ProductPage`, `ProductInput`, `ProductUpdateInput`, `VariantInput`, and functions `getProducts(query)`, `getProduct(id)`, `createProduct(input)`, `updateProduct(id, input)`, `deleteProduct(id)`, `getBrands()`, `getUnits()`, `getCategories()`.

- [ ] **Step 1: Write failing tests**

Cover:
- `getProducts({ page: 2, pageSize: 25, search: "mon" })` calls `api.get("/api/product/products", { params: { page: 2, pageSize: 25, search: "mon" } })` and returns `{ items, pagination }` from the envelope.
- `createProduct(input)` posts to `/api/product/products` with a `variants` array and returns `response.data.data`.
- `updateProduct(7, input)` puts to `/api/product/products/7` **without** a `variants` key.
- `deleteProduct(7)` calls `api.delete("/api/product/products/7")`.
- `getBrands()` / `getUnits()` / `getCategories()` hit `/api/product/brands|units|categories` and unwrap `data`.
- `getApiErrorMessage` handles `{ status, error: { message } }`, `{ detail }`, and an Axios `message`.

- [ ] **Step 2: Run to verify failure**

Run: `cd frontend && pnpm test -- products-api api`
Expected: FAIL (old endpoint/type assertions, missing helpers).

- [ ] **Step 3: Implement**

Key shapes:

```ts
export interface ProductVariant {
  id: number
  sku: string
  barcode: string | null
  options: Record<string, string> | null
  sellingPrice: string
  costPrice: string | null
}

export interface Product {
  id: number
  type: "product"
  attributes: {
    name: string
    description: string | null
    brand: { id: number; name: string }
    unit: { id: number; code: string }
    category: { id: number; name: string } | null
    variants: ProductVariant[]
    createdAt: string | null
    updatedAt: string | null
  }
}

export interface ProductPagination {
  currentPage: number
  totalPages: number
  pageSize: number
  totalItems: number
  links: Record<string, string>
}

export interface ProductPage {
  items: Product[]
  pagination: ProductPagination
}

export interface VariantInput {
  sku: string
  costPrice: string
  sellingPrice: string
  barcode: string | null
}

export interface ProductInput {
  name: string
  brandId: number
  unitId: number
  categoryId: number | null
  description: string | null
  variants: VariantInput[]
}

export type ProductUpdateInput = Omit<ProductInput, "variants">
```

`getProducts` throws when `response.data.status !== "success"` or `data` is missing. `getProductsTotal()` is deleted; the table uses `pagination.totalItems`. Whole-project `tsc -b` is expected to stay red until Task 7 replaces the old consumers.

- [ ] **Step 4: Run tests and commit**

```bash
cd frontend && pnpm test -- products-api api
git add frontend/src/services/products-api.tsx frontend/src/services/products-api.test.ts frontend/src/lib/api.tsx frontend/src/lib/api.test.ts
git commit -m "feat(products): talk to the v1 product API"
```

---

### Task 6: Rewrite product form validation around variants

**Files:**
- Modify: `frontend/src/lib/product-validation.ts`
- Modify: `frontend/src/lib/product-validation.test.ts`

**Interfaces:**
- Produces: `VariantFormValues`, `ProductFormValues`, `VariantFormErrors`, `ProductFormErrors`, `createEmptyVariant()`, `createEmptyProductForm()`, `toProductFormValues(product)`, `toProductInput(values)`, `toProductUpdateInput(values)`, `validateProductForm(values)`.

- [ ] **Step 1: Write failing tests**

- Empty form errors: `name`, `brandId`, `unitId`, `variantsMessage`; `variants` has one entry with `sku/costPrice/sellingPrice` errors.
- Invalid money `12.555`, `-1`, `abc` → `"Use a number with at most 2 decimals."`.
- Duplicate SKUs (`" ABC "` and `"abc"`) mark the second variant's `sku`.
- Removing all variants → `variantsMessage = "Add at least one variant."`.
- `toProductInput` trims, converts ids to numbers, `categoryId: ""` → `null`, `barcode: ""` → `null`.
- `toProductUpdateInput` omits `variants`.
- `toProductFormValues(product)` reads `attributes.*` and maps variants to strings.

- [ ] **Step 2: Run to verify failure**

Run: `cd frontend && pnpm test -- product-validation`
Expected: FAIL.

- [ ] **Step 3: Implement**

```ts
export interface VariantFormValues {
  sku: string
  costPrice: string
  sellingPrice: string
  barcode: string
}

export interface ProductFormValues {
  name: string
  brandId: string
  unitId: string
  categoryId: string
  description: string
  variants: VariantFormValues[]
}

export interface VariantFormErrors {
  sku?: string
  costPrice?: string
  sellingPrice?: string
  barcode?: string
}

export interface ProductFormErrors {
  name?: string
  brandId?: string
  unitId?: string
  categoryId?: string
  description?: string
  variantsMessage?: string
  variants?: VariantFormErrors[]
}
```

`MONEY = /^\d{1,10}(\.\d{1,2})?$/`. Duplicate detection uses `sku.trim().toLowerCase()`.

- [ ] **Step 4: Run tests and commit**

```bash
cd frontend && pnpm test -- product-validation
git add frontend/src/lib/product-validation.ts frontend/src/lib/product-validation.test.ts
git commit -m "feat(products): validate product variants"
```

---

### Task 7: Build the v1 Products UI (multi-variant form, table, page)

**Files:**
- Modify: `frontend/src/components/products/product-form-sheet.tsx`
- Create: `frontend/src/components/products/delete-product-dialog.tsx`
- Modify: `frontend/src/components/products/product-table.tsx`
- Modify: `frontend/src/components/products/product-command-palette.tsx`
- Modify: `frontend/src/pages/dashboard/products/page.tsx`
- Delete: `frontend/src/components/products/product-status-dialog.tsx`
- Delete: `frontend/src/components/products/product-create.tsx`
- Delete: `frontend/src/components/products/product-form-dialog.tsx`
- Add (shadcn): `frontend/src/components/ui/empty.tsx`

**Interfaces:**
- Consumes: Task 5 API functions, Task 6 form helpers, `TablePagination` from `@/components/common/table-pagination`.
- Produces: `<ProductFormSheet open product brands units categories onOpenChange onSaved />`, `<DeleteProductDialog product onOpenChange onDeleted />`, and a Products page with server pagination, search, reference filters, create/edit/delete.

- [ ] **Step 1: Add the shadcn empty component**

Run: `cd frontend && pnpm exec shadcn add empty -y`
Expected: `src/components/ui/empty.tsx` created.

- [ ] **Step 2: Implement the form**

Product fields (2-column grid): name, brand `Select` (from `brands`), unit `Select` (from `units`), category `Select` (`"__none__"` = no category), description `Textarea`. Pass `items` as an id→label map so `SelectValue` renders names rather than raw ids.

Variant section (`FieldSet` + `FieldLegend "Variants"`):
- One bordered block per `values.variants[index]`, titled `Variant {index + 1}`.
- Fields per block: `SKU`, `Cost price`, `Selling price`, `Barcode (optional)`.
- `Remove` button (`variant="ghost"`, `Trash2Icon`) hidden when `variants.length === 1`.
- `Add variant` button (`variant="outline"`, `PlusIcon`) appends `createEmptyVariant()`.
- Edit mode: render the product's variants read-only with `FieldDescription` "Variants can’t be changed yet." and do not submit them.
- Submit: create → `createProduct(input)`; edit → `updateProduct(id, toProductUpdateInput(values))`.
- Keep the existing responsive Sheet (mobile) / Dialog (desktop) split and toasts.

- [ ] **Step 3: Implement the delete dialog**

`AlertDialog` with destructive action, calls `deleteProduct(product.id)`, toasts on success/failure, closes and calls `onDeleted()`.

- [ ] **Step 4: Rewrite the table**

Columns: `Product` (name + `N variants`), `Brand`, `Category`, `Unit`, `SKUs` (first SKU + `+N`), `Selling price` (range from variants), `Actions`.

States: skeleton rows, error row with Retry, `<Empty>` for no products, rows with an actions `DropdownMenu` (Edit, Delete).

- [ ] **Step 5: Rewrite the page**

- Load reference data once: `getBrands()`, `getUnits()`, `getCategories()`; on failure show an error + retry.
- Load products on `[page, pageSize, search, brandId, categoryId, reloadKey]`, keep `pagination`.
- Header: title, description, `Add product` button.
- Toolbar inside a shadcn `Card`: search `InputGroup` (debounced 300ms), brand and category `Select` filters, result count.
- `TablePagination` using `pagination.totalItems`; clamp page after delete with `getPageAfterDelete`.
- `ProductFormSheet` for create/edit; `DeleteProductDialog` for delete; `ProductCommandPalette` for ⌘K.

- [ ] **Step 6: Update the command palette**

Filter on `name`, `brand.name`, `category?.name`, and variant SKUs; actions: Create product, Edit product, Delete product.

- [ ] **Step 7: Type-check, lint, test, build**

```bash
cd frontend && pnpm exec tsc -b --pretty false && pnpm lint && pnpm test && pnpm build
```

Expected: clean.

- [ ] **Step 8: Commit**

```bash
git add frontend/src/components/products frontend/src/components/ui/empty.tsx frontend/src/pages/dashboard/products/page.tsx
git commit -m "feat(products): v1 dashboard with multi-variant create form"
```

---

### Task 8: Route `/api/product/*` to `/api/v1/*`

**Files:**
- Modify: `infrastructure/gateway/nginx.conf`
- Modify: `frontend/vite.config.ts`
- Modify: `frontend/README.md`

**Interfaces:**
- Produces: browser path `/api/product/products` → service path `/api/v1/products`.

- [ ] **Step 1: Update the gateway**

Replace the `/api/product` block with:

```nginx
location /api/product/ {
    set $upstream product-service-web:80;
    proxy_pass http://$upstream/api/v1/;
}
```

- [ ] **Step 2: Update the Vite proxy**

Drop `/api/products` and `/api/product` from the always-on proxy. Keep `/api` → gateway. Add, only when `VITE_PRODUCTS_API_URL` is set, a first entry:

```ts
"/api/product": {
  target: env.VITE_PRODUCTS_API_URL,
  changeOrigin: true,
  rewrite: (path) => path.replace(/^\/api\/product/, "/api/v1"),
},
```

- [ ] **Step 3: Update the README table**

Document `/api/product/*` → `product-service` with the `/api/v1` rewrite.

- [ ] **Step 4: Rebuild the gateway and verify**

```bash
docker compose build api-gateway
docker compose up -d --force-recreate api-gateway
curl -s "http://127.0.0.1:8081/api/product/products?page=1&pageSize=2"
```

Expected: `{"status":"success","data":[...]}`.

- [ ] **Step 5: Commit**

```bash
git add infrastructure/gateway/nginx.conf frontend/vite.config.ts frontend/README.md
git commit -m "fix(gateway): route product dashboard traffic to the v1 API"
```

---

### Task 9: End-to-end verification

- [ ] **Step 1: Backend suite**

Run: `cd services/product-service && php vendor/bin/phpunit`
Expected: all tests pass.

- [ ] **Step 2: Seed and read through the gateway**

```bash
docker compose exec -T products-service php bin/console doctrine:fixtures:load --no-interaction
curl -s "http://127.0.0.1:8081/api/product/products?page=1&pageSize=10"
curl -s "http://127.0.0.1:8081/api/product/brands"
```

Expected: seeded products (one with 2+ variants), 3 brands.

- [ ] **Step 3: Create a two-variant product through the gateway**

```bash
curl -s -X POST "http://127.0.0.1:8081/api/product/products" \
  -H 'Content-Type: application/json' \
  -d '{"name":"E2E Product","brandId":1,"unitId":1,"variants":[{"sku":"E2E-A","costPrice":"1.00","sellingPrice":"2.00"},{"sku":"E2E-B","costPrice":"1.50","sellingPrice":"3.00"}]}'
```

Expected: `201`, `attributes.variants` has 2 entries.

- [ ] **Step 4: Frontend gates**

```bash
cd frontend && pnpm test && pnpm lint && pnpm build
```

Expected: all pass.

- [ ] **Step 5: Browser smoke test**

Open `http://127.0.0.1:5173/dashboard/products`, confirm the seeded rows, click **Add product**, fill the product fields, click **Add variant** twice, save, and confirm the new row shows 3 variants.
