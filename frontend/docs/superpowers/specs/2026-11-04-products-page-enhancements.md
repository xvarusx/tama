# Products Page Enhancements — Design

## Intent

Enhance the existing Products page with:
1. Fix the API endpoint from `/api/product` to `/api/products` for the list endpoint
2. Add advanced Shadcn UI components (Data Table with sorting/filtering, Command palette, Sheet for mobile, Skeletons)
3. Update Vite proxy to route product-service requests directly

## Goals

- Correct the API endpoint to match the product service specification
- Add TanStack Table with server-side sorting, filtering, and pagination
- Add Command palette (Cmd+K) for quick product search/actions
- Add Sheet component for mobile-friendly product forms
- Add Skeleton loaders for better perceived performance
- Update Vite proxy to route `/api/products` and `/api/product/*` to product-service

## Non-goals

- Backend changes (product-service)
- Inventory/stock features
- Bulk actions
- Image upload

## Constraints

- The product service API at `/api/products` returns plain array without total count
- Total count must be derived by walking pages at limit=100
- Prices are stored as integers (whole numbers)
- Status values are `active` and `archive`
- The update endpoint requires full payload (PUT)

## Approaches

### 1. Fix API Endpoint (Chosen)
Update `products-api.tsx` to use `/api/products` for GET list and keep `/api/product/` for create/update.

### 2. Enhanced Data Table with TanStack Table (Chosen)
Replace the simple table with TanStack Table for:
- Column sorting (client-side on current page)
- Column filtering
- Column visibility toggling
- Row selection
- Better pagination controls

### 3. Command Palette (Chosen)
Add Cmd+K command palette for:
- Quick product search
- Quick actions (create, archive, activate)
- Keyboard navigation

### 4. Sheet for Mobile Forms (Chosen)
Replace Dialog with Sheet on mobile for better UX.

### 5. Skeleton Loaders (Chosen)
Add skeleton rows while loading.

### 6. Vite Proxy Update (Chosen)
Add specific proxy entries for product-service.

## Parts

### 1. Fix API Endpoint (`src/services/products-api.tsx`)
- Change `getProducts` to use `/api/products` instead of `/api/product`
- Keep `/api/product/` for create and `/api/product/{id}` for update

### 2. Vite Proxy (`vite.config.ts`)
- Add proxy for `/api/products` → product-service (port 8003)
- Add proxy for `/api/product` → product-service (port 8003)
- Keep existing `/api` proxy for other services via gateway

### 3. Enhanced Product Table (`src/components/products/product-table.tsx`)
- Migrate to TanStack Table
- Add sorting, filtering, column visibility
- Add skeleton loading rows
- Keep existing dropdown actions

### 4. Command Palette (`src/components/products/product-command-palette.tsx`)
- New component using Shadcn Command
- Search products by name/SKU
- Quick actions

### 5. Sheet for Mobile (`src/components/products/product-form-sheet.tsx`)
- New component using Shadcn Sheet
- Responsive: Dialog on desktop, Sheet on mobile

### 6. Product Page Updates (`src/pages/dashboard/products/page.tsx`)
- Integrate new components
- Add command palette trigger
- Use Sheet on mobile

## Verification

- `pnpm test` passes
- `pnpm build` passes
- Product page loads with correct API endpoint
- Sorting, filtering work on current page data
- Command palette opens with Cmd+K
- Mobile form uses Sheet
- Skeleton loaders show during fetch