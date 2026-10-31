# Frontend Auth + Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sign users in through the Symfony auth service using an httpOnly cookie, guard every `/dashboard` URL, and give the signed-in user a TailAdmin-style dashboard with products, clients, inventory, and notifications pages.

**Architecture:** A Next.js BFF: route handlers under `app/api/auth/*` call the auth service, keep the opaque token in an httpOnly cookie, and expose only the user. A root `proxy.ts` does an optimistic check for `/dashboard/*`; a React-cached data-access layer verifies `/api/me` before the dashboard shell renders. Dashboard pages use typed sample data for this pass.

**Tech Stack:** Next.js 16.4.1 (App Router, `cacheComponents`), React 19, TypeScript strict, Tailwind 4, pnpm, Vitest, ApexCharts.

**Spec:** `docs/superpowers/specs/2026-10-23-frontend-auth-design.md`

## Global Constraints

- `Files:` blocks use repo-root-relative paths; commands run from `frontend/`. The repo root is `/home/oussema/Work/tries/2026-09-20-microservices-symfony/erp`.
- Package manager is `pnpm`; do not use npm.
- Next 16 uses `proxy.ts` at the app root; `middleware.ts` is deprecated.
- Auth service base URL: `AUTH_SERVICE_URL`, default `http://127.0.0.1:8001` (gateway is down).
- The backend token is opaque and must only live in the `auth_session` httpOnly cookie; never return it from a Next route handler or put it in client state.
- Login throttling is per-IP and all logins share the Next server's IP; surface the backend message and accept the limitation.
- Unverified accounts are allowed to sign in; copy must not claim verification is required.
- Dashboard data is sample data only; no calls to client/product/inventory/notification services.
- Do not add `next-intl`, `@svgr/webpack`, `flatpickr`, `react-dnd`, `simplebar-react`, `clsx`, or `tailwind-merge`.
- `cn()` is a local helper; icons are inline React components, not SVG imports.
- Cards/tables follow the TailAdmin classes already present in `app/globals.css` (`text-theme-sm`, `border-gray-200`, `dark:bg-white/3`, `shadow-theme-xs`, etc.).
- Every task ends with `pnpm lint` and a commit; tasks that touch pages also run `pnpm build`.

## Review Focus

- A search with no matches shows an empty-state row and stable pagination, not a crash or blank page.
- An unknown `/dashboard/clients/[slug]` returns the Next 404 page, not a stack trace.
- A tampered or expired cookie produces one clean redirect to `/login` with the cookie cleared, with no redirect loop.
- The auth service being down shows a retryable dashboard error and a readable login message; it must not log the user out or trap them on `/dashboard`.
- A failed login never leaks the backend token or an internal error, and an invalid payload gets a 400/422 with a readable message.

---

### Task 1: Test harness and pure auth helpers

**Files:**
- Modify: `frontend/package.json` (scripts + dev dependency)
- Create: `frontend/vitest.config.ts`
- Create: `frontend/tests/auth-helpers.test.ts`
- Create: `frontend/lib/auth/config.ts`
- Create: `frontend/lib/auth/errors.ts`
- Create: `frontend/lib/auth/paths.ts`
- Create: `frontend/lib/auth/cookie-options.ts`

**Interfaces:**
- Produces:
  - `AUTH_SERVICE_URL: string`
  - `SESSION_COOKIE = "auth_session"`
  - `SESSION_MAX_AGE_SECONDS = 604800`
  - `getAuthErrorMessage(status: number, body: unknown, fallback?: string): string`
  - `isProtectedPath(pathname: string): boolean`
  - `getSessionCookieOptions(remember: boolean): SessionCookieOptions`

- [ ] **Step 1: Add Vitest**

Run: `pnpm add -D vitest`

Add to `package.json` scripts: `"test": "vitest run"`.

Create `vitest.config.ts`:

```ts
import { fileURLToPath } from "node:url";
import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    environment: "node",
    include: ["tests/**/*.test.ts"],
  },
  resolve: {
    alias: { "@": fileURLToPath(new URL(".", import.meta.url)) },
  },
});
```

- [ ] **Step 2: Write the failing tests**

Create `tests/auth-helpers.test.ts` with these cases:

```ts
import { describe, expect, it } from "vitest";
import { getAuthErrorMessage } from "@/lib/auth/errors";
import { isProtectedPath } from "@/lib/auth/paths";
import { getSessionCookieOptions } from "@/lib/auth/cookie-options";

describe("getAuthErrorMessage", () => {
  it("uses generic copy for 401", () => {
    expect(getAuthErrorMessage(401, { message: "Invalid credentials." })).toBe(
      "Invalid email or password.",
    );
  });
  it("uses generic copy for 429", () => {
    expect(getAuthErrorMessage(429, null)).toBe(
      "Too many attempts. Please try again later.",
    );
  });
  it("passes through the backend message for 409 and 422", () => {
    expect(getAuthErrorMessage(409, { message: "Email exists." })).toBe("Email exists.");
    expect(getAuthErrorMessage(422, { message: "Password too short." })).toBe(
      "Password too short.",
    );
  });
  it("uses service copy for 5xx", () => {
    expect(getAuthErrorMessage(503, { message: "boom" })).toBe(
      "The authentication service is unavailable. Please try again.",
    );
  });
  it("falls back for unknown status and unreadable body", () => {
    expect(getAuthErrorMessage(418, "nope", "Custom fallback.")).toBe("Custom fallback.");
  });
});

describe("isProtectedPath", () => {
  it("matches dashboard and nested dashboard paths only", () => {
    expect(isProtectedPath("/dashboard")).toBe(true);
    expect(isProtectedPath("/dashboard/clients/acme")).toBe(true);
    expect(isProtectedPath("/dashboardx")).toBe(false);
    expect(isProtectedPath("/login")).toBe(false);
  });
});

describe("getSessionCookieOptions", () => {
  it("sets a 7-day max age when remembered", () => {
    expect(getSessionCookieOptions(true)).toEqual({
      httpOnly: true,
      secure: false,
      sameSite: "lax",
      path: "/",
      maxAge: 604800,
    });
  });
  it("omits maxAge for a session cookie", () => {
    const options = getSessionCookieOptions(false);
    expect(options).not.toHaveProperty("maxAge");
    expect(options.httpOnly).toBe(true);
  });
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `pnpm exec vitest run tests/auth-helpers.test.ts`
Expected: FAIL because `@/lib/auth/*` does not exist.

- [ ] **Step 4: Implement the helpers**

`lib/auth/config.ts`:

```ts
export const AUTH_SERVICE_URL =
  process.env.AUTH_SERVICE_URL ?? "http://127.0.0.1:8001";
export const SESSION_COOKIE = "auth_session";
export const SESSION_MAX_AGE_SECONDS = 60 * 60 * 24 * 7;
```

`lib/auth/errors.ts`: export `getAuthErrorMessage`. Rules: 401/403 → `"Invalid email or password."`; 429 → `"Too many attempts. Please try again later."`; >=500 → `"The authentication service is unavailable. Please try again."`; 409/422 → `body.message` when it is a non-empty string, else `fallback`; anything else → `body.message` when readable, else `fallback`.

`lib/auth/paths.ts`: `isProtectedPath` returns true for `/dashboard` and paths starting `/dashboard/`, false otherwise.

`lib/auth/cookie-options.ts`:

```ts
import { SESSION_MAX_AGE_SECONDS } from "./config";

export type SessionCookieOptions = {
  httpOnly: true;
  secure: boolean;
  sameSite: "lax";
  path: "/";
  maxAge?: number;
};

export function getSessionCookieOptions(remember: boolean): SessionCookieOptions {
  const options: SessionCookieOptions = {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
  };
  if (remember) options.maxAge = SESSION_MAX_AGE_SECONDS;
  return options;
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `pnpm exec vitest run tests/auth-helpers.test.ts`
Expected: PASS (all cases).

- [ ] **Step 6: Lint and commit**

```bash
pnpm lint
git add frontend/package.json frontend/pnpm-lock.yaml frontend/vitest.config.ts frontend/tests/auth-helpers.test.ts frontend/lib/auth
git commit -m "test: add frontend auth helpers and vitest harness"
```

---

### Task 2: Server auth plumbing (session, backend client, DAL)

**Files:**
- Create: `frontend/lib/auth/session.ts`
- Create: `frontend/lib/auth/backend.ts`
- Create: `frontend/lib/auth/dal.ts`
- Delete: `frontend/utils/jwt.tsx`
- Delete: `frontend/utils/proxy.tsx`

**Interfaces:**
- Consumes: `AUTH_SERVICE_URL`, `SESSION_COOKIE`, `getSessionCookieOptions`, `getAuthErrorMessage`.
- Produces:
  - `getSessionToken(): Promise<string | null>`
  - `createSession(token: string, remember: boolean): Promise<void>`
  - `destroySession(): Promise<void>`
  - `authRequest(path: string, options?: { method?: string; body?: unknown; token?: string }): Promise<Response>`
  - `type AuthUser = { id: number; email: string; roles: string[] }`
  - `getCurrentUser(): Promise<AuthUser>` (React-`cache`d)

- [ ] **Step 1: Add the server-only marker and implement `lib/auth/session.ts`**

Run: `pnpm add server-only`

Start `session.ts` with `import "server-only";` then use `cookies` from `next/headers` (async). `getSessionToken` returns `(await cookies()).get(SESSION_COOKIE)?.value ?? null`. `createSession` awaits the cookie store and calls `.set(SESSION_COOKIE, token, getSessionCookieOptions(remember))`. `destroySession` calls `.delete(SESSION_COOKIE)`.

- [ ] **Step 2: Implement `lib/auth/backend.ts`**

`import "server-only";` and export `authRequest`. Build the URL with `new URL(path, AUTH_SERVICE_URL)`, set `Accept: application/json`, set `Content-Type: application/json` only when a body exists, set `X-AUTH-TOKEN` when `token` is provided, serialize the body, and pass `cache: "no-store"`. Default method is `GET`.

- [ ] **Step 3: Implement `lib/auth/dal.ts`**

`import "server-only";` import `cache` from `react`, `redirect` from `next/navigation`, `authRequest`, `getSessionToken`. Export `AuthUser`. Export:

```ts
export const getCurrentUser = cache(async (): Promise<AuthUser> => {
  const token = await getSessionToken();
  if (!token) redirect("/login");
  const response = await authRequest("/api/me", { token });
  if (response.status === 401) redirect("/api/auth/clear");
  if (!response.ok) {
    throw new Error(`Authentication service returned ${response.status}`);
  }
  const user = (await response.json()) as Partial<AuthUser>;
  if (typeof user.id !== "number" || typeof user.email !== "string") {
    throw new Error("Authentication service returned an invalid user.");
  }
  return {
    id: user.id,
    email: user.email,
    roles: Array.isArray(user.roles) ? user.roles : ["ROLE_USER"],
  };
});
```

- [ ] **Step 4: Delete the dead files and verify compilation**

Run: `rm frontend/utils/jwt.tsx frontend/utils/proxy.tsx && pnpm lint && pnpm test`
Expected: PASS; no missing-module errors.

- [ ] **Step 5: Commit**

```bash
git add -A frontend/lib/auth frontend/utils frontend/package.json frontend/pnpm-lock.yaml
git commit -m "feat: add cookie session, auth backend client, and DAL"
```

---

### Task 3: Auth route handlers, proxy, and dashboard error boundary

**Files:**
- Create: `frontend/app/api/auth/login/route.ts`
- Create: `frontend/app/api/auth/logout/route.ts`
- Create: `frontend/app/api/auth/register/route.ts`
- Create: `frontend/app/api/auth/clear/route.ts`
- Create: `frontend/proxy.ts`
- Modify: `frontend/app/page.tsx`
- Create: `frontend/app/(dashboard)/error.tsx`
- Modify (temporary placeholders): `frontend/app/(dashboard)/dashboard/layout.tsx`, `frontend/app/(dashboard)/dashboard/clients/page.tsx`, `frontend/app/(dashboard)/dashboard/clients/[slug]/page.tsx`

**Interfaces:**
- Consumes: `authRequest`, `createSession`, `destroySession`, `getSessionToken`, `getAuthErrorMessage`, `isProtectedPath`, `SESSION_COOKIE`.
- Produces route contracts:
  - `POST /api/auth/login` → `{ user: { id, email, roles } }` or `{ error: string }`
  - `POST /api/auth/logout` → `{ ok: true }`
  - `POST /api/auth/register` → `{ user: { id, email } }` (201) or `{ error: string }`
  - `GET /api/auth/clear` → redirect to `/login`

- [ ] **Step 1: Implement the login handler**

Parse the JSON body defensively. Require non-empty string `email` and `password`; otherwise return 400 `{ error: "Email and password are required." }`. Call `authRequest("/api/user_login", { method: "POST", body: { email: email.trim(), password } })`. If the response is not ok, return `NextResponse.json({ error: getAuthErrorMessage(response.status, data, "Invalid email or password.") }, { status: response.status })`. If the payload lacks `token` or `user`, return 502 `{ error: "Unexpected response from the authentication service." }`. Call `createSession(data.token, body.remember === true)` and return `{ user: { id, email, roles } }` with roles defaulting to `["ROLE_USER"]`.

- [ ] **Step 2: Implement logout, register, and clear**

`logout`: read the token; if present, `try { await authRequest("/api/user_logout", { method: "POST", token }) } catch {}`; always `destroySession()`; return `{ ok: true }`.

`register`: require a valid-looking email and password length >= 8, else return 422 `{ error: "A valid email and a password between 8 and 4096 characters are required." }`. Forward to `/api/user_register`; on failure return the mapped error at the backend status; on success return 201 `{ user: { id, email } }`.

`clear`: `await destroySession()` then `NextResponse.redirect(new URL("/login", request.url))`.

- [ ] **Step 3: Implement the proxy**

Create `frontend/proxy.ts`:

```ts
import { NextResponse, type NextRequest } from "next/server";
import { SESSION_COOKIE } from "@/lib/auth/config";
import { isProtectedPath } from "@/lib/auth/paths";

export function proxy(request: NextRequest) {
  const hasSession = Boolean(request.cookies.get(SESSION_COOKIE)?.value);
  if (isProtectedPath(request.nextUrl.pathname) && !hasSession) {
    return NextResponse.redirect(new URL("/login", request.url));
  }
  return NextResponse.next();
}

export const config = { matcher: ["/dashboard/:path*"] };
```

- [ ] **Step 4: Update `/`, add the error boundary, and make the empty files compile**

`app/page.tsx` becomes async: if `await getSessionToken()` returns a token, `redirect("/dashboard")`, else `redirect("/login")`; export `const instant = false;`.

`app/(dashboard)/error.tsx` is a client component receiving `{ error, retry }`. Log the error once with `useEffect(() => console.error(error), [error])`, then render a centered card: "We couldn't load your dashboard", the message "The authentication service may be unavailable. Please try again.", and a "Try again" button calling `retry`. It sits at the route-group level so it also catches errors thrown by the dashboard layout.

Three files are currently empty and would fail `next build`. Give them temporary minimal exports; Task 7 replaces the layout and Task 10 replaces the clients pages:

```tsx
// app/(dashboard)/dashboard/layout.tsx
export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return children;
}
```

```tsx
// app/(dashboard)/dashboard/clients/page.tsx
export default function ClientsPage() {
  return <h1>Clients</h1>;
}
```

```tsx
// app/(dashboard)/dashboard/clients/[slug]/page.tsx
export default function ClientDetailPage() {
  return <h1>Client</h1>;
}
```

- [ ] **Step 5: Build and exercise the real auth flow**

Run: `pnpm lint && pnpm build`.
Expected: PASS. The build must not call the auth service.

Then start the app: `pnpm dev` (background) and run, substituting a valid account or registering a fresh one first:

```bash
curl -sS -X POST http://127.0.0.1:8001/api/user_register \
  -H 'Content-Type: application/json' \
  -d '{"email":"frontend-check@example.com","password":"StrongPassword123"}'

curl -i -c /tmp/auth.cookies -X POST http://127.0.0.1:3000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"frontend-check@example.com","password":"StrongPassword123","remember":true}'
```

Expected: 200, `{ "user": ... }`, no `token` key, and a `Set-Cookie: auth_session=...; HttpOnly; ... SameSite=lax; Max-Age=604800`.

```bash
curl -i -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard   # 200 (may still show the error boundary until Task 7)
curl -i http://127.0.0.1:3000/dashboard                          # 307 to /login
curl -i -b /tmp/auth.cookies -X POST http://127.0.0.1:3000/api/auth/logout  # 200
curl -i -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard     # 307 to /login
```

- [ ] **Step 6: Commit**

```bash
git add frontend/app/api frontend/app/page.tsx frontend/app/\(dashboard\)/error.tsx frontend/app/\(dashboard\)/dashboard frontend/proxy.ts
git commit -m "feat: add auth route handlers and dashboard proxy guard"
```

---

### Task 4: Wire the login and register forms

**Files:**
- Modify: `frontend/components/auth/SignInForm.tsx`
- Modify: `frontend/components/auth/SignUpForm.tsx`
- Create: `frontend/components/auth/LogoutButton.tsx`
- Modify: `frontend/components/ui/button/Button.tsx`

**Interfaces:**
- Consumes: `POST /api/auth/login`, `POST /api/auth/register`, `POST /api/auth/logout`.
- Produces: `LogoutButton({ className?: string })` — a client button that logs out and lands on `/login`.

- [ ] **Step 1: Extend Button with a submit type**

Add optional `type?: "button" | "submit" | "reset"` to `ButtonProps` and pass it to the native `<button>` (default `"button"`).

- [ ] **Step 2: Update the login form**

Keep the email/password/remember state. On submit: disable the button, set error empty, POST `{ email, password, remember }` and read `{ user }` on success or `{ error }` on failure. On success `router.replace("/dashboard"); router.refresh()`. On failure show `data.error` in a `role="alert"` block with error styling. Add `autoComplete="email"` and `autoComplete="current-password"`. Remove the unused `ChevronLeftIcon` import. Button label becomes "Signing in..." while pending.

- [ ] **Step 3: Update the register form**

Remove the first/last name fields. Add email/password names, `autoComplete="email"` and `autoComplete="new-password"`, and required fields. Submit to `/api/auth/register`. Disable while pending. On success replace the form with a success panel: "Account created. You can sign in now." plus a link to `/login`. On failure show the `error` message. Keep the terms checkbox required.

- [ ] **Step 4: Add the logout button**

```tsx
"use client";
// POST /api/auth/logout, then router.replace("/login") and router.refresh()
```

Show "Signing out..." while pending and disable the button.

- [ ] **Step 5: Lint, build, and verify the failure paths**

Run: `pnpm lint && pnpm build`.

With the dev server running:

```bash
curl -i -X POST http://127.0.0.1:3000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"frontend-check@example.com","password":"wrong"}'
```

Expected: 401 with `{ "error": "Invalid email or password." }` and no cookie.

- [ ] **Step 6: Commit**

```bash
git add frontend/components/auth frontend/components/ui/button/Button.tsx
git commit -m "feat: wire login, registration, and logout forms"
```

---

### Task 5: UI primitives, dependencies, icons, and TailAdmin styles

**Files:**
- Modify: `frontend/package.json` (dependencies)
- Modify: `frontend/app/globals.css`
- Create: `frontend/utils/cn.ts`
- Create: `frontend/components/ui/badge/Badge.tsx`
- Create: `frontend/components/ui/table/index.tsx`
- Create: `frontend/components/ui/dropdown/Dropdown.tsx`
- Create: `frontend/components/ui/dropdown/DropdownItem.tsx`
- Create: `frontend/components/common/PageBreadCrumb.tsx`
- Create: `frontend/components/common/ThemeToggleButton.tsx`
- Create: `frontend/components/tables/Pagination.tsx`
- Create: `frontend/hooks/useClickOutside.ts`
- Modify: `frontend/icons/index.tsx`

**Interfaces:**
- Produces:
  - `cn(...classes: Array<string | false | null | undefined>): string`
  - `Badge`, `Table`, `TableHeader`, `TableBody`, `TableRow`, `TableCell`
  - `Dropdown({ isOpen, onClose, children, className? })`
  - `DropdownItem({ tag?, href?, onItemClick?, className?, children })`
  - `PageBreadcrumb({ pageTitle })`
  - `Pagination({ currentPage, totalPages, onPageChange })`

- [ ] **Step 1: Install chart dependencies**

Run: `pnpm add apexcharts react-apexcharts`
Expected: `package.json` and `pnpm-lock.yaml` updated.

- [ ] **Step 2: Add the `cn` helper and styles**

`utils/cn.ts`: join truthy class names with a space. Append to `app/globals.css` only the missing TailAdmin utilities copied from `/home/oussema/Work/tries/2026-10-22-nextjsdashboard/free-nextjs-admin-dashboard/src/app/globals.css`: the `menu-item`, `menu-item-active`, `menu-item-inactive`, `menu-item-icon`, `menu-item-icon-active`, `menu-item-icon-inactive`, `menu-item-text`, `menu-item-arrow` block, and `custom-scrollbar`. Do not replace the existing theme tokens.

- [ ] **Step 3: Port the primitives**

Copy from the template and change `@/i18n/navigation` imports to `next/link` / `next/navigation`: `Badge`, the table primitives, `Dropdown`, `DropdownItem`, `PageBreadcrumb`, `ThemeToggleButton`, `Pagination`, and `useClickOutside`. `PageBreadcrumb` links Home to `/dashboard`.

- [ ] **Step 4: Add the icons**

Append inline React SVG components to `icons/index.tsx`, converting the markup from these template files:

- `src/icons/grid.svg` → `GridIcon`
- `src/icons/box-cube.svg` → `BoxCubeIcon`
- `src/icons/user-circle.svg` → `UserCircleIcon`
- `src/icons/box.svg` → `BoxIcon`
- `src/icons/bell.svg` → `BellIcon`
- `src/icons/chevron-down.svg` → `ChevronDownIcon`
- `src/icons/arrow-up.svg` → `ArrowUpIcon`
- `src/icons/arrow-down.svg` → `ArrowDownIcon`
- `src/icons/group.svg` → `GroupIcon`
- `src/icons/box-line.svg` → `BoxIconLine`
- `src/icons/horizontal-dots.svg` → `HorizontaLDots`
- `src/icons/more-dot.svg` → `MoreDotIcon`
- `src/icons/close.svg` → `CloseIcon`

Every icon accepts `SVGProps<SVGSVGElement>`, uses `currentColor` where the source does, and has no fixed `fill` that blocks Tailwind colors.

- [ ] **Step 5: Lint and build**

Run: `pnpm lint && pnpm build`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add frontend/package.json frontend/pnpm-lock.yaml frontend/app/globals.css frontend/utils/cn.ts frontend/components/ui frontend/components/common frontend/components/tables frontend/hooks frontend/icons/index.tsx
git commit -m "feat: port dashboard UI primitives, icons, and styles"
```

---

### Task 6: Typed sample data and pure helpers

**Files:**
- Create: `frontend/lib/dashboard-data.ts`
- Create: `frontend/tests/dashboard-data.test.ts`

**Interfaces:**
- Produces:
  - `type DashboardClient`, `DashboardProduct`, `DashboardInventoryItem`, `DashboardNotification`
  - `clients: DashboardClient[]`, `products: DashboardProduct[]`, `inventory: DashboardInventoryItem[]`, `notifications: DashboardNotification[]`
  - `filterByQuery<T>(items: T[], query: string, fields: (item: T) => string[]): T[]`
  - `paginate<T>(items: T[], page: number, pageSize: number): { items: T[]; totalPages: number; page: number }`
  - `getInventoryStatus(item: DashboardInventoryItem): "out" | "low" | "ok"`
  - `computeInventorySummary(items: DashboardInventoryItem[]): { totalSkus: number; lowStock: number; outOfStock: number; totalUnits: number }`
  - `getUnreadCount(items: DashboardNotification[]): number`

- [ ] **Step 1: Write the failing tests**

Create `tests/dashboard-data.test.ts` covering:

```ts
expect(filterByQuery(products, "POS", (p) => [p.name, p.sku]).length).toBeGreaterThan(0);
expect(filterByQuery(products, "zzzz", (p) => [p.name])).toEqual([]);
expect(paginate([1, 2, 3, 4, 5, 6, 7], 1, 5)).toMatchObject({ items: [1, 2, 3, 4, 5], totalPages: 2, page: 1 });
expect(paginate([], 1, 5)).toEqual({ items: [], totalPages: 1, page: 1 });
expect(paginate([1, 2, 3], 99, 5)).toMatchObject({ page: 1 });
expect(getInventoryStatus({ ...inventory[0], quantity: 0 })).toBe("out");
expect(getInventoryStatus({ ...inventory[0], quantity: 1, reorderLevel: 5 })).toBe("low");
expect(getInventoryStatus({ ...inventory[0], quantity: 50, reorderLevel: 5 })).toBe("ok");
expect(getUnreadCount(notifications)).toBe(notifications.filter((n) => !n.read).length);
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `pnpm exec vitest run tests/dashboard-data.test.ts`
Expected: FAIL because the module does not exist.

- [ ] **Step 3: Implement the data and helpers**

Use these shapes:

```ts
export type DashboardClient = {
  id: number; slug: string; name: string; company: string; email: string;
  phone: string; status: "active" | "inactive"; since: string;
  city: string; country: string; orders: number; totalSpent: number;
  recentActivity: { date: string; description: string }[];
};

export type DashboardProduct = {
  id: number; name: string; sku: string;
  category: string; price: number; stock: number;
  status: "active" | "draft" | "archived";
};

export type DashboardInventoryItem = {
  id: number; sku: string; product: string; warehouse: string;
  quantity: number; reorderLevel: number;
};

export type DashboardNotification = {
  id: number; title: string; message: string;
  type: "order" | "stock" | "client" | "system";
  time: string; read: boolean; href?: string;
};
```

Populate 6 clients (including slugs `acme-corporation`, `northwind-traders`, `globex`, `initech`, `umbrella-health`, `stark-industries`), 8 products, 8 inventory rows, and 6 notifications. At minimum: `clients[0]` must be named "Acme Corporation" with slug `acme-corporation`; one product must be named "POS Terminal" with SKU `POS-1001` so the helper test's `"POS"` query matches. Use fixed human-readable `time` strings such as `"5 min ago"`; never call `Date.now()` at module scope or during render. Keep `notifications` with exactly 3 unread.

Implement `filterByQuery` case-insensitively and return the original array when the query is blank. Implement `paginate` with `totalPages = Math.max(1, Math.ceil(items.length / pageSize))` and clamp the requested page into `[1, totalPages]`. Implement `getInventoryStatus` with `quantity === 0` → `"out"`, `quantity <= reorderLevel` → `"low"`, else `"ok"`. Implement the summary and unread count directly.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `pnpm exec vitest run tests/dashboard-data.test.ts`
Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
pnpm lint
git add frontend/lib/dashboard-data.ts frontend/tests/dashboard-data.test.ts
git commit -m "feat: add typed dashboard sample data and helpers"
```

---

### Task 7: Sidebar, header, shell, and authenticated dashboard layout

**Files:**
- Create: `frontend/context/SidebarContext.tsx`
- Create: `frontend/context/NotificationsContext.tsx`
- Create: `frontend/components/layout/AppSidebar.tsx`
- Create: `frontend/components/layout/AppHeader.tsx`
- Create: `frontend/components/layout/Backdrop.tsx`
- Create: `frontend/components/layout/DashboardShell.tsx`
- Create: `frontend/components/header/NotificationDropdown.tsx`
- Create: `frontend/components/header/UserDropdown.tsx`
- Modify: `frontend/app/(dashboard)/dashboard/layout.tsx`

**Interfaces:**
- Consumes: `getCurrentUser`, `LogoutButton`, `notifications`, `getUnreadCount`.
- Produces:
  - `SidebarProvider`, `useSidebar()` with `isExpanded`, `isMobileOpen`, `isHovered`, `toggleSidebar`, `toggleMobileSidebar`, `setIsHovered`
  - `NotificationsProvider`, `useNotifications()` with `{ items: DashboardNotification[]; markAllRead: () => void }`
  - `DashboardShell({ email, children })`
  - `AppSidebar`, `AppHeader({ email })`, `NotificationDropdown`, `UserDropdown({ email })`

- [ ] **Step 1: Port the sidebar context and backdrop**

Copy the template's `src/context/SidebarContext.tsx`, replacing `@/i18n/navigation` with `next/navigation` and dropping the unused active-item/submenu state if it is not needed. Keep: expanded, mobile open, hovered, resize listener, and close-on-route-change. Port `src/layout/Backdrop.tsx` unchanged except imports.

- [ ] **Step 2: Add the notifications context**

Create `context/NotificationsContext.tsx` as a client provider seeded with the static `notifications` array. It holds `items` in state and exposes `markAllRead()` which maps every item to `read: true`. `useNotifications()` throws when used outside the provider. This keeps the header dot and the notifications page in sync.

- [ ] **Step 3: Build the sidebar**

Ported from `src/layout/AppSidebar.tsx` without `next-intl`. Nav items in order:

```ts
const navItems = [
  { label: "Dashboard", href: "/dashboard", icon: <GridIcon /> },
  { label: "Products", href: "/dashboard/products", icon: <BoxCubeIcon /> },
  { label: "Clients", href: "/dashboard/clients", icon: <UserCircleIcon /> },
  { label: "Inventory", href: "/dashboard/inventory", icon: <BoxIcon /> },
  { label: "Notifications", href: "/dashboard/notifications", icon: <BellIcon /> },
];
```

Use `usePathname`; a link is active when `pathname === href` for `/dashboard`, and `pathname === href || pathname.startsWith(href + "/")` for the rest. Use `/images/logo/auth-logo.svg` for the logo (the only logo in the project). No submenus, no "Pro" badges, no sidebar widget.

- [ ] **Step 4: Build the header and dropdowns**

`AppHeader` props `{ email: string }`: sidebar toggle, a visual search input (`aria-label="Search"`), `ThemeToggleButton`, `NotificationDropdown`, `UserDropdown`. Match the template's sticky header classes.

`NotificationDropdown` calls `useNotifications()` and shows the first 3 items plus a "View all notifications" link to `/dashboard/notifications`. The dot appears when unread items exist. Use `useState` for open state and the ported `Dropdown`.

`UserDropdown` props `{ email: string }`: shows the email, a "Dashboard" link, and `<LogoutButton />` styled as a dropdown item. Do not invent a name or avatar; use a neutral initial badge derived from the email.

- [ ] **Step 5: Build the shell and layout**

`DashboardShell` is a client component wrapping `ThemeProvider` (the existing `context/ThemeContext.tsx`), then `NotificationsProvider`, then `SidebarProvider`, with an inner component that reads `useSidebar()` and applies the same margin classes as the template (`lg:ml-[290px]` vs `lg:ml-[90px]`). Render `<AppSidebar />`, `<Backdrop />`, `<AppHeader email={email} />`, and `<div className="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">{children}</div>`.

`app/(dashboard)/dashboard/layout.tsx` is an async server component:

```tsx
export const instant = false;

export default async function DashboardLayout({ children }: { children: React.ReactNode }) {
  const user = await getCurrentUser();
  return <DashboardShell email={user.email}>{children}</DashboardShell>;
}
```

- [ ] **Step 6: Verify the guard and shell**

Run: `pnpm lint && pnpm build`.

With dev running and a fresh login cookie:

```bash
curl -s -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard | grep -q "Dashboard"
curl -s -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard | grep -q "frontend-check@example.com"
```

Expected: both greps succeed. Without the cookie, the request returns a 307 to `/login`.

- [ ] **Step 7: Commit**

```bash
git add frontend/context frontend/components/layout frontend/components/header frontend/app/\(dashboard\)/dashboard/layout.tsx
git commit -m "feat: add authenticated dashboard shell, sidebar, and header"
```

---

### Task 8: Dashboard overview page

**Files:**
- Modify: `frontend/app/(dashboard)/dashboard/page.tsx`
- Create: `frontend/components/dashboard/MetricCard.tsx`
- Create: `frontend/components/dashboard/OrdersChart.tsx`
- Create: `frontend/components/dashboard/RevenueChart.tsx`
- Create: `frontend/components/dashboard/RecentClientsTable.tsx`
- Create: `frontend/components/dashboard/LowStockTable.tsx`
- Create: `frontend/components/dashboard/UnreadNotificationsCard.tsx`

**Interfaces:**
- Consumes: `clients`, `products`, `inventory`, `notifications`, `computeInventorySummary`, `getInventoryStatus`, `getUnreadCount`, `useNotifications`.
- Produces: `MetricCard({ label, value, change?, trend?, icon })` and `UnreadNotificationsCard()`.

- [ ] **Step 1: Build the metric card**

```tsx
type MetricCardProps = {
  label: string;
  value: string;
  change?: string;
  trend?: "up" | "down";
  icon: React.ReactNode;
};
```

Use the template's card classes (`rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3`), the icon in a `bg-gray-100 dark:bg-gray-800` square, and a `Badge` with `ArrowUpIcon`/`ArrowDownIcon`.

- [ ] **Step 2: Build the unread-notifications card**

`UnreadNotificationsCard` is a client component using `useNotifications()` and `getUnreadCount(items)`, returning a `MetricCard` labelled "Unread notifications" with value from the live context state (so marking notifications read updates the dashboard). Import `BellIcon`.

- [ ] **Step 3: Build the charts**

Both are client components using `dynamic(() => import("react-apexcharts"), { ssr: false })`. `OrdersChart` is a blue bar chart over the 12 months; `RevenueChart` is a two-series line/area chart (revenue vs orders). Use the template's options (`MonthlySalesChart.tsx`, `StatisticsChart.tsx`) with plain English month labels and sample series. Disable the toolbar and data labels, keep tooltips and the legend.

- [ ] **Step 4: Build the tables**

`RecentClientsTable` shows the first 5 clients with name, company, status badge, and total spent. `LowStockTable` filters inventory to `getInventoryStatus(item) !== "ok"` and shows SKU, product, quantity, reorder level, and a warning/error badge. Both link to their full pages (`/dashboard/clients`, `/dashboard/inventory`).

- [ ] **Step 5: Compose the page**

`app/(dashboard)/dashboard/page.tsx` renders:

- `<PageBreadcrumb pageTitle="Dashboard" />`
- four metric cards: Total clients, Products, Low stock, Unread notifications (`UnreadNotificationsCard`)
- `OrdersChart` and `RevenueChart`
- `RecentClientsTable` and `LowStockTable` in a responsive `grid grid-cols-12 gap-4 md:gap-6`

- [ ] **Step 6: Verify**

Run: `pnpm lint && pnpm build`.
With the dev server: `curl -s -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard | grep -q "Total clients"`.
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add frontend/app/\(dashboard\)/dashboard/page.tsx frontend/components/dashboard
git commit -m "feat: build dashboard overview with charts and tables"
```

---

### Task 9: Products page

**Files:**
- Create: `frontend/app/(dashboard)/dashboard/products/page.tsx`
- Create: `frontend/components/dashboard/ProductsTable.tsx`

**Interfaces:**
- Consumes: `products`, `filterByQuery`, `paginate`, `Badge`, `Table*`, `Pagination`.
- Produces: `ProductsTable()` client component.

- [ ] **Step 1: Build the table component**

Client component with `const PAGE_SIZE = 5`, `query` state, and `page` state. Filter on `name`, `sku`, and `category`. Reset to page 1 whenever the query changes. Render a search input, a responsive table (Product, SKU, Category, Price, Stock, Status), a status badge (`active` success, `draft` warning, `archived` light), an empty row spanning all columns when nothing matches ("No products match your search."), and `Pagination` only when `totalPages > 1`. Format prices with `Intl.NumberFormat("en-US", { style: "currency", currency: "USD" })`.

- [ ] **Step 2: Build the page**

Server component with `metadata` title "Products", `<PageBreadcrumb pageTitle="Products" />`, and `<ProductsTable />`.

- [ ] **Step 3: Verify**

Run: `pnpm lint && pnpm build`.
With the dev server: `curl -s -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard/products | grep -q "Products"`.
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add frontend/app/\(dashboard\)/dashboard/products frontend/components/dashboard/ProductsTable.tsx
git commit -m "feat: add products page"
```

---

### Task 10: Clients list and client detail

**Files:**
- Modify: `frontend/app/(dashboard)/dashboard/clients/page.tsx`
- Create: `frontend/components/dashboard/ClientsTable.tsx`
- Modify: `frontend/app/(dashboard)/dashboard/clients/[slug]/page.tsx`

**Interfaces:**
- Consumes: `clients`, `filterByQuery`, `paginate`, `Badge`, `Table*`, `Pagination`.
- Produces: `ClientsTable()`, client detail route.

- [ ] **Step 1: Build the clients table**

Same client-side pattern as products (`PAGE_SIZE = 5`), filtering on `name`, `company`, `email`, and `city`. Columns: Client (name + email), Company, Phone, Status, Since, and a "View" link to `/dashboard/clients/${slug}`. Empty state: "No clients match your search."

- [ ] **Step 2: Build the list page**

Server component with metadata title "Clients", page breadcrumb, and `<ClientsTable />`.

- [ ] **Step 3: Build the detail page**

Next 16 passes `params` as a Promise:

```tsx
export default async function ClientDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const client = clients.find((item) => item.slug === slug);
  if (!client) notFound();
  // profile card, contact card, activity list
}
```

Use `notFound()` from `next/navigation`. The profile card shows name, company, status badge, city/country, orders, and total spent. The contact card shows email and phone. The activity card lists `recentActivity` with date and description. Include a "Back to clients" link.

- [ ] **Step 4: Verify**

Run: `pnpm lint && pnpm build`.

```bash
curl -s -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard/clients | grep -q "Clients"
curl -s -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard/clients/acme-corporation | grep -q "Acme"
curl -s -o /dev/null -w "%{http_code}" -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard/clients/does-not-exist
```

Expected: first two greps succeed; the last command prints `404`.

- [ ] **Step 5: Commit**

```bash
git add frontend/app/\(dashboard\)/dashboard/clients frontend/components/dashboard/ClientsTable.tsx
git commit -m "feat: add clients list and detail pages"
```

---

### Task 11: Inventory page

**Files:**
- Create: `frontend/app/(dashboard)/dashboard/inventory/page.tsx`
- Create: `frontend/components/dashboard/InventoryTable.tsx`
- Create: `frontend/components/dashboard/InventorySummary.tsx`

**Interfaces:**
- Consumes: `inventory`, `filterByQuery`, `paginate`, `getInventoryStatus`, `computeInventorySummary`, `MetricCard`.
- Produces: `InventoryTable()`, `InventorySummary()`.

- [ ] **Step 1: Build the summary cards**

Server/client-safe component rendering three `MetricCard`s from `computeInventorySummary(inventory)`: Total SKUs, Low stock, Out of stock. Use `BoxIcon`/`BoxIconLine` and warning/error badges.

- [ ] **Step 2: Build the table**

Client component with `PAGE_SIZE = 5`, search over `sku`, `product`, and `warehouse`, plus a status filter (`all`, `ok`, `low`, `out`). Columns: SKU, Product, Warehouse, Quantity, Reorder level, Status. Use success/warning/error badges for `ok`/`low`/`out`. Empty state: "No inventory matches your filters."

- [ ] **Step 3: Build the page**

Server component with metadata title "Inventory", page breadcrumb, `<InventorySummary />`, and `<InventoryTable />`.

- [ ] **Step 4: Verify**

Run: `pnpm lint && pnpm build`.
With the dev server: `curl -s -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard/inventory | grep -q "Inventory"`.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add frontend/app/\(dashboard\)/dashboard/inventory frontend/components/dashboard/InventoryTable.tsx frontend/components/dashboard/InventorySummary.tsx
git commit -m "feat: add inventory page"
```

---

### Task 12: Notifications page

**Files:**
- Create: `frontend/app/(dashboard)/dashboard/notifications/page.tsx`
- Create: `frontend/components/dashboard/NotificationsList.tsx`

**Interfaces:**
- Consumes: `useNotifications()`, `getUnreadCount`, `BellIcon`/`BoxIcon`/`GroupIcon`.
- Produces: `NotificationsList()` client component.

- [ ] **Step 1: Build the list**

Client component calling `useNotifications()` for `{ items, markAllRead }`. Each row shows a type icon, title, message, time, and an unread dot when `!read`. Rows with `href` render as `Link`; the rest are static. Include a "Mark all as read" button calling `markAllRead`, disabled when `getUnreadCount(items) === 0`. Keep the list empty state ("You're all caught up.") for robustness.

- [ ] **Step 2: Build the page**

Server component with metadata title "Notifications", `<PageBreadcrumb pageTitle="Notifications" />`, and `<NotificationsList />`.

- [ ] **Step 3: Verify**

Run: `pnpm lint && pnpm build`.
With the dev server: `curl -s -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard/notifications | grep -q "Notifications"`.
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add frontend/app/\(dashboard\)/dashboard/notifications frontend/components/dashboard/NotificationsList.tsx
git commit -m "feat: add notifications page"
```

---

### Task 13: Final verification

**Files:**
- Modify: only files needed for fixes found below.

- [ ] **Step 1: Run the full checks**

```bash
pnpm test
pnpm lint
pnpm build
```

Expected: all pass with no TypeScript or ESLint errors.

- [ ] **Step 2: Run the end-to-end auth flow**

Ensure the auth service is running at `http://127.0.0.1:8001` and `pnpm dev` is running at `http://127.0.0.1:3000`. Then:

```bash
# Clean state
rm -f /tmp/auth.cookies

# Guard
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://127.0.0.1:3000/dashboard
# expect 307 and a /login redirect

# Login (use a real account; register one first if needed)
curl -sS -c /tmp/auth.cookies -X POST http://127.0.0.1:3000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"frontend-check@example.com","password":"StrongPassword123","remember":true}'
# expect { user: ... } and no token

# Pages
for path in /dashboard /dashboard/products /dashboard/clients /dashboard/clients/acme-corporation /dashboard/inventory /dashboard/notifications; do
  curl -s -b /tmp/auth.cookies "http://127.0.0.1:3000$path" | grep -q "Dashboard\|Products\|Clients\|Inventory\|Notifications" || echo "FAILED $path"
done

# Logout invalidates and clears
curl -s -b /tmp/auth.cookies -X POST http://127.0.0.1:3000/api/auth/logout
curl -s -o /dev/null -w "%{http_code}\n" -b /tmp/auth.cookies http://127.0.0.1:3000/dashboard
# expect 307
```

- [ ] **Step 3: Verify the stale-cookie path**

```bash
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" \
  -H 'Cookie: auth_session=definitely-not-a-real-token' \
  http://127.0.0.1:3000/dashboard
```

Expected: a redirect to `/api/auth/clear` (directly or through the rendering path), then `/login`; no redirect loop. If Next renders the 200 shell before streaming the redirect, confirm `/api/auth/clear` clears the cookie and that a second request redirects to `/login`.

- [ ] **Step 4: Verify the auth-service-down path**

Stop the auth service (or point `AUTH_SERVICE_URL` at a closed port) and request `/dashboard` with a valid cookie.
Expected: the dashboard error boundary renders "We couldn't load your dashboard" with a working "Try again" button; the cookie is still present; `/login` remains reachable.

- [ ] **Step 5: Browser pass**

Open `http://127.0.0.1:3000`, sign in, walk every sidebar link, collapse the sidebar, resize to mobile and open the drawer, toggle dark mode, open both header dropdowns, mark notifications read, search each table with a matching and a non-matching query, open a client detail, and sign out. Expected: no console errors, no layout breakage, and the redirect behavior above.

- [ ] **Step 6: Commit any fixes**

```bash
git add -A
git commit -m "chore: finish auth and dashboard integration"
```
