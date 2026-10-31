# Clients CRUD + Pagination — Design

## Intent

The dashboard Clients page has three unfinished controls: pagination, rows-per-page,
and Add Client. The row action menu's Edit and Delete items are also inert. Make all
of them work against the existing client service API.

## Goals

- Server-driven pagination with page numbers and a rows-per-page selector.
- Create a client, edit a client, and delete a client with confirmation.
- Loading, empty, and error states with retry and toast feedback.
- Keep the current shadcn visual language and add any missing UI primitives from
  the shadcn registry.

## Non-goals

- Backend changes of any kind.
- Search, sorting, filtering, or bulk actions.
- Optimistic UI updates.

## Constraints

- `GET /api/clients?page=&limit=` returns a plain array; there is no total count.
  `limit` is capped at 100 by the backend.
- `limit` must be requested at 100 to derive an exact total, which costs one
  request per 100 rows plus one final request for the short page. Total is
  refreshed on mount, page change, limit change, and after every mutation.
- `POST /api/clients` and `PUT /api/clients/{id}` expect `name, email, phone,
  address, type, cifId`. `name` and `type` are required by the database; email is
  validated by the entity, so the form validates it too.
- Client list is sorted by `name ASC`, so a new client can land on any page.

## Chosen approach

**Page-owned state.** `src/pages/dashboard/clients/page.tsx` is the single source of
truth for `page`, `limit`, rows, total, status, error, and refresh. The table,
pagination, and dialogs are presentational and receive props/callbacks.

Alternatives considered:

- A `ClientsProvider` context: less prop passing, but more indirection than this
  small page needs.
- Independent fetch per component: duplicates requests and makes post-mutation
  refresh error-prone.

## Parts

### Service (`src/services/clients-api.tsx`)

- Keep `Client` and `getClients(page, limit)`.
- Add `ClientInput` (`name, email, phone, address, type, cifId`).
- Add `createClient(input)`, `updateClient(id, input)`, `deleteClient(id)`.
- Add `getClientsTotal()` that walks pages at `limit = 100` until a short page.
- Normalize API errors with a shared helper so dialogs can show a message.

### Page (`src/pages/dashboard/clients/page.tsx`)

- Fetch rows and total whenever `page`, `limit`, or `refreshKey` changes.
- Clamp `page` when the total shrinks below the current page.
- Handlers: `changePage`, `changeLimit` (resets to page 1), `refresh`.
- After create: refresh and go to page 1.
- After edit: refresh the current page.
- After delete: if the deleted row was the last on a page greater than 1, go back
  one page; otherwise refresh.
- Renders `CreateClient`, `ClientsTable`, `ClientsPagination`, plus a single
  `AlertDialog` for delete confirmation.

### Table (`client-table.tsx`)

- Presentational: receives rows/status/error and `onRetry`, `onEdit(client)`,
  `onDelete(client)`.
- Edit/Delete dropdown items call those callbacks instead of being inert.

### Form dialog (`client-form-dialog.tsx`, new)

- Shared by create and edit, opened by page state (`mode: create | edit`,
  `client?: Client`).
- Controlled inputs with inline validation: name and type required, email must be
  valid when non-empty.
- Submit is disabled while saving; shows the API error if the request fails.
- Type is a select with `Entreprise` and `Particulier`.

### Create (`client-create.tsx`)

- Keeps the current "Add Client" button and opens the form dialog.

### Pagination (`client-pagination.tsx`)

- Controlled: receives `page`, `limit`, `total`, `onPageChange`, `onLimitChange`.
- Rows-per-page options: 10, 25, 50, 100.
- Numeric pages with first/last and ellipses; previous/next disabled at bounds.

### shadcn additions

- `dialog`, `alert-dialog`, `textarea` added with the shadcn CLI using the local
  `components.json` configuration.
- Mount the existing `Toaster` once in `main.tsx`; use it for success and failure
  messages.

## Left to the builder

- Exact validation copy and dialog layout.
- Whether the table shows a skeleton or the existing loading row.
- Toast wording.
