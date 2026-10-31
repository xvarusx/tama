# Clients CRUD + Pagination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the Clients page controls and add create, edit, and delete for a single client.

**Architecture:** `src/pages/dashboard/clients/page.tsx` owns page, limit, data, total, and refresh state. The table, pagination bar, and dialogs are presentational and receive props/callbacks. The API service gains create/update/delete calls and a total-count walk.

**Tech Stack:** React 19, TypeScript, Vite 8, Tailwind 4, shadcn `base-sera` (Base UI primitives), Axios, Vitest.

**Spec:** `docs/superpowers/specs/2026-10-26-clients-crud-pagination-design.md`

## Global Constraints

- Do not modify the Symfony backend. It runs at `http://127.0.0.1:8001`.
- Use the shared `api` Axios instance with `baseURL: CLIENTS_API_URL` for every client request.
- Backend `limit` is capped at 100. Rows-per-page options are exactly 10, 25, 50, and 100.
- Client `type` values are `entreprise` and `particulier`, shown as `Entreprise` and `Particulier`.
- Page owns state. Components do not fetch clients themselves.
- This directory is not a git repository. Skip the commit steps from the skill template.
- Use `pnpm` for dependency and script commands.
- Toasts use the manager exported from `src/components/ui/toast.tsx`: `toast.add({ title, description, type })`. `<Toaster />` is mounted once in `src/main.tsx`.

## Review Focus

- Deleting the only row on page > 1 must move to the previous page, not leave an empty page.
- Creating a client while on a later page must jump to page 1 and show the new client.
- A total that is exactly a multiple of 100 must terminate the count walk at the empty next page.
- Changing rows per page while deep in the list must reset to page 1.
- An empty optional email is valid and becomes `null`; an invalid non-empty email is blocked before the request.
- A failed save or delete must keep the dialog open and show the API message.

---

### Task 1: Unblock the build and add shadcn primitives

**Files:**
- Modify: `tsconfig.app.json`
- Modify: `eslint.config.js`
- Modify: `src/hooks/use-mobile.ts`
- Create: `src/components/ui/dialog.tsx`
- Create: `src/components/ui/alert-dialog.tsx`
- Create: `src/components/ui/textarea.tsx`
- Modify: `src/main.tsx`

**Interfaces:**
- Consumes: existing `components.json` (`base-sera`, `@/components/ui`).
- Produces: `Dialog`, `DialogContent`, `DialogHeader`, `DialogFooter`, `DialogTitle`, `DialogDescription`; `AlertDialog`, `AlertDialogContent`, `AlertDialogHeader`, `AlertDialogFooter`, `AlertDialogTitle`, `AlertDialogDescription`, `AlertDialogCancel`, `AlertDialogAction`; `Textarea`; a mounted `<Toaster />`.

- [ ] **Step 1: Silence the TypeScript 6 `baseUrl` deprecation**

Add `"ignoreDeprecations": "6.0"` to `compilerOptions` in `tsconfig.app.json`. The current build fails with TS5101 before compiling any source.

- [ ] **Step 2: Clear the existing pagination type errors**

`client-pagination.tsx` currently has four errors that block the build: unused `page` and `setPage`, an implicitly-typed `hundleChange`, and an invalid `onChange` prop on Base UI's `Select`. Remove the unused `useState` line, the `hundleChange` function, and the `onChange` prop. Task 5 replaces this file with the real implementation.

- [ ] **Step 3: Clear the baseline lint errors**

`pnpm lint` currently fails on generated shadcn files and `use-mobile.ts`.

In `eslint.config.js`, add an override that disables `react-refresh/only-export-components` for `src/components/ui/**/*.{ts,tsx}` and `src/components/themes/theme-provider.tsx`. These files intentionally co-export variants, managers, and hooks with components.

In `src/hooks/use-mobile.ts`, initialize state with `useState(() => window.innerWidth < MOBILE_BREAKPOINT)` and remove the synchronous `setIsMobile(...)` call from the effect body. Keep the media-query listener callback.

- [ ] **Step 4: Verify the build and lint are green before feature work**

Run: `pnpm build && pnpm lint`
Expected: both exit 0.

- [ ] **Step 5: Add the registry components**

Run: `pnpm dlx shadcn@4.22.1 add dialog alert-dialog textarea --yes`
Expected: three new files under `src/components/ui/`, no existing files overwritten.

- [ ] **Step 6: Mount the toast provider**

In `src/main.tsx`, import `Toaster` from `@/components/ui/toast` and render `<Toaster />` inside `ThemeProvider`, next to `<ModeToggle />`.

- [ ] **Step 7: Verify**

Run: `pnpm build && pnpm lint`
Expected: both exit 0.

---

### Task 2: Add Vitest and the pure helpers

**Files:**
- Modify: `package.json` (add dev dependency and `test` script)
- Modify: `src/services/clients-api.tsx` (add the input types only)
- Create: `vitest.config.ts`
- Create: `src/lib/pagination.ts`
- Create: `src/lib/pagination.test.ts`
- Create: `src/lib/client-validation.ts`
- Create: `src/lib/client-validation.test.ts`

**Interfaces:**
- Consumes: `ClientInput` from `@/services/clients-api`, added in Step 0 of this task.

- Produces:
  - `interface ClientInput` and `const CLIENT_TYPES` in `src/services/clients-api.tsx`
  - `type PageItem = number | "ellipsis-start" | "ellipsis-end"`
  - `getPageItems(page: number, totalPages: number): PageItem[]`
  - `clampPage(page: number, totalPages: number): number`
  - `getPageAfterDelete(page: number, rowCount: number): number`
  - `type ClientFormValues`, `type ClientFormErrors`
  - `createEmptyClientForm(): ClientFormValues`
  - `toClientFormValues(client: { name: string; email: string | null; phone: string | null; address: string | null; type: string; cifId: string | null }): ClientFormValues`
  - `validateClientForm(values: ClientFormValues): ClientFormErrors`
  - `toClientInput(values: ClientFormValues): ClientInput`

- [ ] **Step 0: Add the shared client input types**

In `src/services/clients-api.tsx`, add `ClientInput` with the six form fields and `CLIENT_TYPES` mapping `entreprise` to `Entreprise` and `particulier` to `Particulier`. Do not add the request functions yet; Task 3 does that.

- [ ] **Step 1: Install and wire Vitest**

Run: `pnpm add -D vitest`
Add `"test": "vitest run"` to `scripts` in `package.json`.
Create `vitest.config.ts` using `defineConfig` from `vitest/config`, aliasing `@` to `src` with `fileURLToPath(new URL("./src", import.meta.url))`, `environment: "node"`, and `include: ["src/**/*.test.ts"]`.

- [ ] **Step 2: Write the failing pagination tests**

Create `src/lib/pagination.test.ts`:

```ts
import { describe, expect, it } from "vitest"
import { clampPage, getPageAfterDelete, getPageItems } from "./pagination"

describe("getPageItems", () => {
  it("lists every page when there are seven or fewer", () => {
    expect(getPageItems(1, 5)).toEqual([1, 2, 3, 4, 5])
  })

  it("adds a trailing ellipsis near the start", () => {
    expect(getPageItems(2, 10)).toEqual([1, 2, 3, 4, 5, "ellipsis-end", 10])
  })

  it("adds both ellipses around a middle page", () => {
    expect(getPageItems(5, 10)).toEqual([
      1, "ellipsis-start", 4, 5, 6, "ellipsis-end", 10,
    ])
  })

  it("adds a leading ellipsis near the end", () => {
    expect(getPageItems(9, 10)).toEqual([1, "ellipsis-start", 6, 7, 8, 9, 10])
  })
})

describe("clampPage", () => {
  it("keeps a valid page", () => expect(clampPage(3, 10)).toBe(3))
  it("clamps below one", () => expect(clampPage(0, 10)).toBe(1))
  it("clamps above the last page", () => expect(clampPage(12, 10)).toBe(10))
  it("never returns less than one when there are no pages", () => {
    expect(clampPage(5, 0)).toBe(1)
  })
})

describe("getPageAfterDelete", () => {
  it("stays put when other rows remain", () => {
    expect(getPageAfterDelete(2, 4)).toBe(2)
  })
  it("goes back when the last row on a later page is deleted", () => {
    expect(getPageAfterDelete(3, 1)).toBe(2)
  })
  it("stays on page one", () => expect(getPageAfterDelete(1, 1)).toBe(1))
})
```

- [ ] **Step 3: Run the pagination tests and confirm they fail**

Run: `pnpm test -- src/lib/pagination.test.ts`
Expected: FAIL because `./pagination` does not exist.

- [ ] **Step 4: Implement `src/lib/pagination.ts`**

Use this algorithm: if `totalPages <= 7`, return `[1..totalPages]`; if `page <= 4`, return `[1..5, "ellipsis-end", totalPages]`; if `page >= totalPages - 3`, return `[1, "ellipsis-start", totalPages-4..totalPages]`; otherwise return `[1, "ellipsis-start", page-1, page, page+1, "ellipsis-end", totalPages]`. `clampPage` is `Math.min(Math.max(1, page), Math.max(1, totalPages))`. `getPageAfterDelete` is `rowCount === 1 && page > 1 ? page - 1 : page`.

- [ ] **Step 5: Write the failing validation tests**

Create `src/lib/client-validation.test.ts`:

```ts
import { describe, expect, it } from "vitest"
import {
  createEmptyClientForm,
  toClientFormValues,
  toClientInput,
  validateClientForm,
} from "./client-validation"

const valid = {
  name: "Ada",
  email: "ada@example.com",
  phone: "0612345678",
  address: "1 Main Street",
  type: "entreprise",
  cifId: "CIF-1",
}

describe("validateClientForm", () => {
  it("accepts a complete form", () => {
    expect(validateClientForm(valid)).toEqual({})
  })

  it("requires a non-blank name", () => {
    expect(validateClientForm({ ...valid, name: "   " })).toEqual({
      name: "Name is required.",
    })
  })

  it("requires a type", () => {
    expect(validateClientForm({ ...valid, type: "" })).toEqual({
      type: "Type is required.",
    })
  })

  it("allows an empty email", () => {
    expect(validateClientForm({ ...valid, email: "" })).toEqual({})
  })

  it("rejects an invalid email", () => {
    expect(validateClientForm({ ...valid, email: "ada@example" })).toEqual({
      email: "Enter a valid email address.",
    })
  })
})

describe("toClientInput", () => {
  it("trims text and turns blanks into null", () => {
    expect(
      toClientInput({
        ...valid,
        name: "  Ada  ",
        email: " ",
        phone: "",
        address: "   ",
        cifId: " ",
      }),
    ).toEqual({
      name: "Ada",
      email: null,
      phone: null,
      address: null,
      type: "entreprise",
      cifId: null,
    })
  })
})

describe("client form mapping", () => {
  it("starts with an entreprise and blank fields", () => {
    expect(createEmptyClientForm()).toEqual({
      name: "",
      email: "",
      phone: "",
      address: "",
      type: "entreprise",
      cifId: "",
    })
  })

  it("maps null API values to empty strings", () => {
    expect(
      toClientFormValues({
        name: "Ada",
        email: null,
        phone: null,
        address: null,
        type: "entreprise",
        cifId: null,
      }),
    ).toEqual({
      name: "Ada",
      email: "",
      phone: "",
      address: "",
      type: "entreprise",
      cifId: "",
    })
  })
})
```

- [ ] **Step 6: Run the validation tests and confirm they fail**

Run: `pnpm test -- src/lib/client-validation.test.ts`
Expected: FAIL because `./client-validation` does not exist.

- [ ] **Step 7: Implement `src/lib/client-validation.ts`**

Email rule: `^[^\s@]+@[^\s@]+\.[^\s@]+$` applied to `email.trim()`, only when non-empty. `toClientInput` trims every string and converts empty email/phone/address/cifId to `null`. `toClientFormValues` converts null to `""`.

- [ ] **Step 8: Run the tests and verify**

Run: `pnpm test`
Expected: PASS for both new test files.

- [ ] **Step 9: Verify types and lint**

Run: `pnpm build && pnpm lint`
Expected: both exit 0.

---

### Task 3: Extend the clients API

**Files:**
- Modify: `src/lib/api.tsx`
- Modify: `src/services/clients-api.tsx`
- Create: `src/services/clients-api.test.ts`

**Interfaces:**
- Consumes: `api` from `@/lib/api`; `ClientInput` and `CLIENT_TYPES` from Task 2 Step 0.
- Produces:
  - `getApiErrorMessage(error: unknown, fallback?: string): string` from `@/lib/api`
  - `getClients(page: number, limit: number): Promise<Client[]>`
  - `getClientsTotal(): Promise<number>`
  - `createClient(input: ClientInput): Promise<Client>`
  - `updateClient(id: number, input: ClientInput): Promise<Client>`
  - `deleteClient(id: number): Promise<void>`

- [ ] **Step 1: Write the failing service tests**

Create `src/services/clients-api.test.ts`. Mock `@/lib/api` with `vi.mock` so only `{ api }` is needed. Use a helper that casts responses: `const response = (data: unknown) => ({ data }) as never`.

Tests:
- `getClientsTotal` returns 125 when page 1 has 100 rows and page 2 has 25, and calls `api.get` with `("/api/clients", { baseURL: CLIENTS_API_URL, params: { page: 1, limit: 100 } })` then page 2.
- `getClientsTotal` returns 100 when page 1 has 100 rows and page 2 is empty (exact-multiple case).
- `getClientsTotal` returns 0 when page 1 is empty.
- `createClient` calls `api.post("/api/clients", input, { baseURL: CLIENTS_API_URL })` and returns `response.data`.
- `updateClient` calls `api.put("/api/clients/7", input, { baseURL: CLIENTS_API_URL })`.
- `deleteClient` calls `api.delete("/api/clients/7", { baseURL: CLIENTS_API_URL })` and resolves `undefined`.

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `pnpm test -- src/services/clients-api.test.ts`
Expected: FAIL because `getClientsTotal`, `createClient`, `updateClient`, and `deleteClient` are not exported.

- [ ] **Step 3: Add `getApiErrorMessage` to `src/lib/api.tsx`**

```ts
export function getApiErrorMessage(
  error: unknown,
  fallback = "Something went wrong. Please try again.",
): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as
      | { error?: string; message?: string }
      | undefined

    return data?.error ?? data?.message ?? error.message
  }

  if (error instanceof Error) return error.message

  return fallback
}
```

- [ ] **Step 4: Extend `src/services/clients-api.tsx`**

Implement the four new functions using `baseURL: CLIENTS_API_URL`. `getClientsTotal` walks pages at `limit = 100`, accumulates lengths, and stops when a page returns fewer than 100 rows. (`ClientInput` and `CLIENT_TYPES` already exist from Task 2 Step 0.)

- [ ] **Step 5: Run the tests and verify**

Run: `pnpm test`
Expected: all service and helper tests PASS.

- [ ] **Step 6: Verify types and lint**

Run: `pnpm build && pnpm lint`
Expected: both exit 0.

---

### Task 4: Add the client dialogs

**Files:**
- Create: `src/components/clients/client-form-dialog.tsx`
- Create: `src/components/clients/delete-client-dialog.tsx`

**Interfaces:**
- Consumes: `createClient`, `updateClient`, `deleteClient`, `CLIENT_TYPES`, `type Client`, `type ClientInput`; `getApiErrorMessage`; `toast`; the Task 1 dialogs and Textarea; the Task 2 helpers.
- Produces:
  - `ClientFormDialog({ open, onOpenChange, client, onSaved })` where `client?: Client | null`, `onSaved: (client: Client, mode: "create" | "edit") => void`.
  - `DeleteClientDialog({ client, onOpenChange, onDeleted })` where `client: Client`, `onDeleted: (client: Client) => void`.

Do not wire these into the page yet; Task 5 does that so the build stays green between tasks.

- [ ] **Step 1: Implement `ClientFormDialog`**

Initialize `values` with `useState(() => client ? toClientFormValues(client) : createEmptyClientForm())` and `errors` with `useState<ClientFormErrors>({})`. The parent mounts this dialog only while it is open, so no reset effect is needed; this also avoids the `react-hooks/set-state-in-effect` lint rule. Fields: name (required), type select, email, phone, CIF, address textarea. Use the ids `client-name`, `client-type`, `client-email`, `client-phone`, `client-cif`, and `client-address`. Show `FieldError` under each invalid field and set `aria-invalid` on the input. Submit runs `validateClientForm`; on errors return without a request. On success call `createClient` or `updateClient`, then `toast.add({ title: "Client created" | "Client updated", description: saved.name, type: "success" })`, call `onSaved(saved, mode)`, and close. On failure set the error text from `getApiErrorMessage(cause, "Unable to save client.")`, add an error toast, and keep the dialog open. Disable the submit button while saving.

Use `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, `DialogFooter`, a `<form onSubmit>`, and a cancel `Button type="button"` that calls `onOpenChange(false)`. The submit button reads `Create client` in create mode and `Save changes` in edit mode.

- [ ] **Step 2: Implement `DeleteClientDialog`**

Render `AlertDialog open` with title `Delete client` and a description naming the client. The destructive `AlertDialogAction` reads `Delete`, uses `variant="destructive"`, and calls `deleteClient(client.id)`; on success add a success toast, call `onOpenChange(false)`, then `onDeleted(client)`. On failure show `getApiErrorMessage(cause, "Unable to delete client.")`, add an error toast, and keep the dialog open with the action disabled while deleting. Use `AlertDialogCancel` with the text `Cancel`.

- [ ] **Step 3: Verify**

Run: `pnpm build && pnpm lint`
Expected: both exit 0. The new files compile even though nothing imports them yet.

---

### Task 5: Wire the pagination, table, create button, and page

**Files:**
- Modify: `src/components/clients/client-pagination.tsx`
- Modify: `src/components/clients/client-table.tsx`
- Modify: `src/components/clients/client-create.tsx`
- Modify: `src/pages/dashboard/clients/page.tsx`

**Interfaces:**
- Consumes: everything produced by Tasks 1–4.
- Produces:
  - `ClientsPagination({ page, limit, total, onPageChange, onLimitChange })` where all values are numbers and both callbacks take numbers.
  - `ClientsTable({ clients, status, error, onRetry, onEdit, onDelete })` where `status: "loading" | "error" | "ready"`, `onEdit: (client: Client) => void`, `onDelete: (client: Client) => void`.
  - `ClientCreate({ onCreated }: { onCreated: () => void })`.
  - The finished Clients page.

These four files change together because the new prop contracts make each other's old call sites fail to compile.

- [ ] **Step 1: Make the pagination bar controlled**

Remove `useState`, the unused `hundleChange`, and the hardcoded page links. Compute `totalPages = Math.max(1, Math.ceil(total / limit))` and `pageItems = getPageItems(page, totalPages)`.

Use the Base UI select API, not the DOM `onChange`: `<Select value={String(limit)} onValueChange={(value) => onLimitChange(Number(value ?? limit))}>`. Keep the four options and the `select-rows-per-page` label.

Render `pageItems`. For numbers, render `PaginationLink` with `isActive={item === page}`, `href="#"`, and `onClick={(event) => { event.preventDefault(); onPageChange(item) }}`. For the two ellipsis strings, render `PaginationEllipsis`. Wire `PaginationPrevious` and `PaginationNext` with the same prevent-default handler and `page <= 1` / `page >= totalPages` guards. Add `aria-disabled` and `className="pointer-events-none opacity-50"` when disabled. Add a small `Page {page} of {totalPages}` label next to the rows-per-page field.

- [ ] **Step 2: Make the table presentational**

Remove `useEffect`, `useState`, `axios`, `getClients`, `getErrorMessage`, and `reloadKey`. Keep the table markup, loading row, error row with retry, empty row, and `COLUMN_COUNT = 7`. Add the four props above.

Wire the dropdown: `<DropdownMenuItem onClick={() => onEdit(client)}>Edit</DropdownMenuItem>` and `<DropdownMenuItem variant="destructive" onClick={() => onDelete(client)}>Delete</DropdownMenuItem>`. Keep the `MoreHorizontalIcon` trigger and its screen-reader label.

- [ ] **Step 3: Wire the Add Client button**

Keep the existing button markup and `IconPlus`. Add local `open` state. On click set open; render `{open && <ClientFormDialog open onOpenChange={setOpen} onSaved={() => { setOpen(false); onCreated() }} />}`.

- [ ] **Step 4: Add the page state and fetch**

State: `page` (1), `limit` (10), `clients` (`Client[]`), `total` (0), `status` (`"loading" | "error" | "ready"`, initial `"loading"`), `error` (""), `reloadKey` (0), `editingClient` (`Client | null`), `deletingClient` (`Client | null`).

In an effect keyed on `[page, limit, reloadKey]`, run `Promise.all([getClients(page, limit), getClientsTotal()])` with a `cancelled` flag. Do not call `setState` synchronously in the effect body; the `react-hooks/set-state-in-effect` lint rule is enabled. Compute `totalPages = Math.max(1, Math.ceil(nextTotal / limit))` inside the `.then`. If `page > totalPages`, call `setPage(totalPages)` and return without setting rows. Otherwise set rows, total, and `ready`. On failure set `getApiErrorMessage(cause, "Unable to load clients.")` and `error` status.

- [ ] **Step 5: Add the handlers**

`beginLoading()`: `setStatus("loading")` and `setError("")`. Call it from every handler that starts a request.

`refresh = () => { beginLoading(); setReloadKey((key) => key + 1) }`.

`changePage(nextPage)`: `beginLoading(); setPage(nextPage)`.

`changeLimit(nextLimit)`: `beginLoading(); setLimit(nextLimit); setPage(1)`.

`handleCreated()`: `beginLoading(); setPage(1); setReloadKey((key) => key + 1)`.

`handleSaved()`: `refresh()` for an edit on the current page.

`handleDeleted()`: compute `nextPage = getPageAfterDelete(page, clients.length)`; `setDeletingClient(null)`; if `nextPage !== page`, `beginLoading(); setPage(nextPage)`, otherwise `refresh()`.

`handleRetry()`: `refresh()`.

- [ ] **Step 6: Render the page**

Keep the existing wrapper div. Render `<CreateClient onCreated={handleCreated} />`, `<ClientsTable clients={clients} status={status} error={error} onRetry={handleRetry} onEdit={setEditingClient} onDelete={setDeletingClient} />`, and `<ClientsPagination page={page} limit={limit} total={total} onPageChange={changePage} onLimitChange={changeLimit} />`. Mount the edit dialog only while editing: `{editingClient && <ClientFormDialog open client={editingClient} onOpenChange={(open) => { if (!open) setEditingClient(null) }} onSaved={() => { setEditingClient(null); handleSaved() }} />}`. Mount the delete dialog only while deleting: `{deletingClient && <DeleteClientDialog client={deletingClient} onOpenChange={(open) => { if (!open) setDeletingClient(null) }} onDeleted={handleDeleted} />}`.

- [ ] **Step 7: Verify**

Run: `pnpm test && pnpm build && pnpm lint`
Expected: all exit 0. The table no longer imports Axios or the service fetcher, and the pagination file no longer uses `useState`.

---

### Task 6: End-to-end smoke test

**Files:**
- Create: `/tmp/clients-e2e.mjs` (throwaway, do not add to the project)

**Interfaces:**
- Consumes: the running dev server, the global Playwright install at `/home/oussema/.local/share/mise/installs/npm-playwright/latest/node_modules/playwright/index.mjs`, and a freshly registered account (the demo fixture is not guaranteed to exist in the running auth service).
- Produces: evidence that the UI works end to end.

- [ ] **Step 1: Start the dev server**

Run: `pnpm dev --host 127.0.0.1` in the background. Wait until `http://127.0.0.1:5173` responds.

- [ ] **Step 2: Write the smoke script**

Use `chromium.launch({ headless: true })`, a 1280×900 viewport, and `page.goto("http://127.0.0.1:5173/signup")`. Register with full name `Test User`, email `e2e-<timestamp>@example.com`, and password `ChangeMe123!` in both password fields. Click `Create Account` and wait for `/dashboard`.

Assertions, in order:
1. The clients table shows rows and a pagination nav (`nav[aria-label="pagination"]`).
2. Click page 2 inside the pagination nav (if present), then click `#select-rows-per-page` and click the `25` option in the popup. Assert the pagination's active link is `1`.
3. Click page 2 inside the pagination nav again (if present), then open the `Add Client` button, leave `#client-name` blank, click `Create client`, and assert `Name is required.` is visible.
4. Fill `#client-name` with `A E2E <timestamp>` and `#client-email` with `bad@`, click `Create client`, and assert `Enter a valid email address.` is visible. Fix the email to `e2e@example.com`, fill `#client-phone`, `#client-address`, and `#client-cif`, click `Create client`, and assert the success toast, the active page is `1`, and the new row is visible.
5. Click that row's `Open menu` button, choose `Edit`, change `#client-name` to `A E2E Edited`, click `Save changes`, and assert the updated row.
6. Click that row's `Open menu` button, choose `Delete`. Before confirming, install `page.route("**/api/clients/*", ...)` that fulfills DELETE requests with status 500 and `{ "error": "Simulated failure" }`. Click the `Delete` action, assert `Simulated failure` is visible and the dialog is still open. Remove the route, click `Delete` again, and assert the success toast and that the row is gone.

The form dialog's ids are `client-name`, `client-email`, `client-phone`, `client-address`, `client-cif`, and `client-type`. The type select defaults to `entreprise`.

- [ ] **Step 3: Run the smoke test**

Run: `node /tmp/clients-e2e.mjs`
Expected: exits 0 with every assertion passing.

- [ ] **Step 4: Stop the dev server and clean up**

Kill the dev server process. If the script failed after creating the client, delete the test client through the UI or `curl -X DELETE http://127.0.0.1:8001/api/clients/<id>`.

---

### Task 7: Final verification

**Files:** none.

- [ ] **Step 1: Run the full gate**

Run: `pnpm test && pnpm build && pnpm lint`
Expected: all exit 0.
