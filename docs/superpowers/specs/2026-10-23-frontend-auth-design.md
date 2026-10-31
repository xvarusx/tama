# Frontend authentication and authorization integration — design

## Intent

Wire the Next.js frontend to the existing Symfony authentication service so that:

- registered users can sign in and sign out,
- every URL under `/dashboard` is reachable only by a signed-in user,
- the backend API token never reaches browser JavaScript.

Role-based access control is explicitly out of scope for now, but the session
shape keeps `roles` so it can be enforced later without reworking the plumbing.

## Current state

- `POST /api/user_login` returns `{ token, user: { id, email, roles } }`.
- `GET /api/me` and `POST /api/user_logout` expect the opaque token in the
  `X-AUTH-TOKEN` header.
- `POST /api/user_register` creates an account; it returns no token and the
  account is unverified until the emailed link is opened.
- The frontend login form posts to a non-existent `/api/auth/login`.
- `utils/proxy.tsx` and `utils/jwt.tsx` are unused leftovers that reference
  missing modules (`jsonwebtoken`, `cookie`, `@/app/lib/session`) and currently
  break type-checking.
- The API gateway (`http://localhost:8081`) routes the auth endpoints but sends
  no CORS headers, so the browser cannot call it directly.

## Approaches considered

1. **HttpOnly cookie BFF (chosen).** Next route handlers call the auth service
   and store the opaque token in an httpOnly cookie. `proxy.ts` performs the
   optimistic guard for `/dashboard/*`; server-side session helpers re-check the
   token against `/api/me` before rendering user data. Cost: backend calls must
   go through the Next server.
2. **Token in localStorage.** Simpler for direct calls, but exposes the token to
   XSS and requires adding CORS to the gateway. Rejected.
3. **Separate encrypted session cookie plus API token.** Adds a second
   credential and rotation logic without changing the security properties of
   option 1. Rejected.

## Decisions

- **Cookie:** `auth_session`, containing only the opaque API token. `httpOnly`,
  `sameSite=lax`, `secure` in production, `path=/`. "Keep me logged in" gives a
  7-day max-age; unchecked gives a session cookie.
- **Login flow:** `POST /api/auth/login` validates input, forwards it to
  `POST {AUTH_SERVICE_URL}/api/user_login`, sets the cookie on success, and
  returns `{ user }` without the token. Backend status/message is forwarded for
  invalid credentials and validation errors; throttling uses a generic message.
- **Request validation:** `POST /api/auth/login` and `/api/auth/register` require
  `Content-Type: application/json` (HTML forms cannot send it) and reject
  requests whose `Origin`/`Sec-Fetch-Site` mark them as cross-site. This closes
  the login-CSRF path while keeping non-browser clients usable.
- **Logout flow:** `POST /api/auth/logout` calls the backend logout with
  `X-AUTH-TOKEN` (best effort), deletes the cookie, and returns 200. The client
  redirects to `/login` and refreshes.
- **Registration flow:** `POST /api/auth/register` forwards email/password to
  `/api/user_register` and returns the backend status/message. No auto-login
  because the service issues no token. The form shows a success state saying the
  account was created and the user can sign in. First/last name fields are
  removed because the API does not accept them.
- **Unverified accounts:** the auth service does not block sign-in for unverified
  users and `/api/me` exposes no verification flag, so the frontend neither
  blocks nor warns. The success copy avoids implying that verification is
  required.
- **Stale sessions:** if a cookie exists but `/api/me` returns 401, the server
  redirects through `GET /api/auth/clear`, which deletes the cookie and sends the
  user to `/login`. For a network error or 5xx, the DAL throws and a dashboard
  error boundary offers retry; the cookie is kept so a transient outage does not
  sign everyone out.
- **Route guard:** root `proxy.ts` (Next 16 convention) redirects unauthenticated
  requests for `/dashboard/:path*` to `/login`. It does not bounce signed-in
  users away from `/login` or `/register`; that avoids a trap when the auth
  service is down or a cookie is stale. The proxy only checks cookie presence;
  the secure check happens in the data-access layer.
- **Data access layer:** `lib/auth/dal.ts` exposes `getCurrentUser()`, memoized
  with React `cache`, which reads the cookie, calls `/api/me`, and redirects on
  missing/invalid sessions. Every dashboard page calls it before rendering user
  data, so a PPR shell cannot emit page content before the redirect resolves.
- **Server-side API client:** `lib/auth/backend.ts` centralizes the base URL and
  `X-AUTH-TOKEN` header so future service calls reuse one place.
- **Roles:** `AuthUser` includes `roles`; no role checks are enforced in this
  change.
- **Configuration:** server-only `AUTH_SERVICE_URL`, defaulting to
  `http://127.0.0.1:8001` because only the auth service is currently running
  directly (the gateway on 8081 is down). Override it in `frontend/.env.local`
  when the gateway is up.
- **Known limitation — login throttling:** the auth service throttles per client
  IP (3 attempts) and does not trust proxy headers. Since all logins now arrive
  from the Next server, everyone shares one throttle bucket. Fixing that needs a
  trusted-proxy contract across the gateway and Symfony, which is out of scope
  for this change; the login error path shows a generic throttling message
  instead of the backend's raw text.
- **Cleanup:** remove `utils/jwt.tsx` and `utils/proxy.tsx`; the proxy logic moves
  to root `proxy.ts`.
- **UI:** dashboard layout gets a header with the signed-in email and a logout
  button. The layout blocks on `getCurrentUser()` and exports `instant = false`,
  which Cache Components allows for a route that is not yet streamed.
  Login and register forms get loading/disabled states, backend error messages,
  and autocomplete attributes.

## Extension: dashboard pages

### Intent

After signing in, the user lands on a working dashboard with the same visual
language as the TailAdmin template at
`/home/oussema/Work/tries/2026-10-22-nextjsdashboard/free-nextjs-admin-dashboard`.
The template has no products, clients, inventory, or notifications pages, so
those four are composed from its table, card, badge, and form primitives.

For this pass the pages display realistic sample data. Wiring them to the
microservices comes later.

### Approach

**Selective port (chosen).** Copy the static layout and UI primitives that fit,
strip `next-intl`, SVGR, and unused demo pages, and rewrite the navigation in
plain Next.js. Build the missing pages from the template's table/card
components. This keeps the look without dragging in calendar, i18n, drag-and-drop,
or file-upload code.

Alternatives considered:

- **Copy the whole admin template.** Pulls in `next-intl`, `@svgr/webpack`,
  calendar, charts, and many demo pages; heavy and most of it is unused.
  Rejected.
- **Design fresh pages.** Less work than a full copy but diverges from the
  TailAdmin look the user asked for. Rejected.

### Routes and content

| Route | Content |
|---|---|
| `/dashboard` | Breadcrumb, four metric cards (clients, products, low stock, notifications), monthly orders bar chart, revenue/orders line chart, recent clients table, low-stock table |
| `/dashboard/products` | Breadcrumb, search field, read-only product table (product, SKU, category, price, stock, status) with pagination |
| `/dashboard/clients` | Breadcrumb, search field, read-only client table (client, company, email, phone, status, since), links to detail |
| `/dashboard/clients/[slug]` | Client profile card, contact details, recent activity/orders |
| `/dashboard/inventory` | Stock metric cards, read-only inventory table (SKU, product, warehouse, quantity, reorder level, status) |
| `/dashboard/notifications` | Notification list with unread indicators, type icons, timestamps, and a client-side "mark all read" action |

Sample data lives in `lib/dashboard-data.ts` (typed, shared by the overview and
list pages) so swapping in real API calls later touches one module.

### Shared shell

- `components/layout/AppSidebar.tsx` — logo, Dashboard / Products / Clients /
  Inventory / Notifications links, collapse + hover + mobile behavior ported from
  the template without i18n.
- `components/layout/AppHeader.tsx` — sidebar toggle, search box (visual only),
  theme toggle, notification dropdown (latest three + "View all"), user dropdown
  with real email and a working logout.
- `context/SidebarContext.tsx`, `components/layout/Backdrop.tsx`,
  `components/header/NotificationDropdown.tsx`,
  `components/header/UserDropdown.tsx`.
- Ported primitives: `Badge`, `Table`, `Dropdown`, `DropdownItem`,
  `PageBreadCrumb`, `Pagination`, `ThemeToggleButton`, plus inline icons copied
  from the template (no SVGR loader).

### Decisions

- **Dependencies:** add `apexcharts` and `react-apexcharts` for the charts. Do
  not add `next-intl`, `@svgr/webpack`, `flatpickr`, `react-dnd`, or
  `simplebar-react`. A local `cn()` helper replaces `clsx`/`tailwind-merge`.
- **Styles:** append only the missing TailAdmin utilities to
  `app/globals.css` (`menu-item*`, `custom-scrollbar`, chart overrides) rather
  than replacing the existing theme tokens.
- **Authentication:** the dashboard layout remains a server component that
  resolves the current user and passes only `{ email }` to the client header,
  keeping the token server-side.
- **UI-only actions:** search fields filter sample rows client-side; "Add"
  buttons are omitted rather than wired to dead links. Notifications "mark all
  read" is client state only.
- **Theme:** the template's dark-mode toggle is kept; it uses the existing
  `ThemeContext`.

### Non-goals for the extension

- Live data from client, product, inventory, or notification services.
- CRUD forms, detail pages for products/inventory, or notification persistence.
- Porting the template's calendar, chart demos, UI-element demos, profile,
  forms, or auth pages.
- Reworking the template's marketing pages or licensing.

## Non-goals

- Role-based authorization and admin screens.
- Password reset (`/reset-password` is linked but has no page or backend
  endpoint).
- Frontend email-verification page. The auth service already emits the signed
  link; note that the gateway currently does not route `/api/verify/email`, so
  verification links need a separate gateway/`DEFAULT_URI` fix.
- Refresh tokens, token expiry, or "remember me" on the backend.

## Verification

- `pnpm lint` and `pnpm build` pass.
- With the stack running: bad login shows the backend message; good login lands
  on `/dashboard`; `/dashboard` and a nested dashboard URL redirect to `/login`
  when the cookie is absent; logout invalidates the token (a follow-up request
  to `/api/me` with the old token returns 401); registration success shows the
  check-email state.
- Every sidebar link reaches its page, tables render the sample rows, the
  sidebar collapses and works on mobile, the theme toggle persists, the
  notification dropdown links to `/dashboard/notifications`, and the user
  dropdown shows the signed-in email and logs out.
