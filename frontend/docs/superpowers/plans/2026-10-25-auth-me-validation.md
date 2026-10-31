# Auth `/me` validation + working logout — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Validate the session against `GET /api/me` before rendering protected routes, and make logout call `POST /api/user_logout`, clear the local cookie, and return to `/`.

**Architecture:** A React context (`AuthProvider`) owns a four-state session machine (`checking | authenticated | anonymous | error`). Route guards read the context instead of trusting cookie presence. `checking` renders a loading screen; `error` renders a retry screen and keeps the cookie; only an explicit `401` clears the cookie.

**Tech Stack:** React 19, react-router-dom 7, axios, Vite 8, TypeScript 6.

**Spec:** Design agreed in-session on 2026-10-25 (short design; backend contract read from `erp/services/authentication-service/src/Controller/ApiLoginController.php`).

## Global Constraints

- Auth header is `X-AUTH-TOKEN` (set by the existing axios request interceptor).
- `GET /api/me` → `200 { id, email, roles }` | `401 { message }`.
- `POST /api/user_logout` → always `200 { message: "Logged out." }`; server nulls the token.
- `POST /api/user_login` → `{ token, user: { id, email, roles } }`.
- Non-401 failure of `/api/me`: keep the cookie, show the retry screen. Do not log out.
- Logout clears local state even when the API call fails.
- There is no test runner in this repo and no git repository: verification is `tsc` plus a real browser session against the backend.

## Review Focus

- **No cookie at all:** must not call `/api/me`, must go straight to the login page.
- **Garbage cookie:** must call `/api/me`, receive 401, delete the cookie, and land on login without a dashboard flash.
- **Backend down / 500 with a cookie present:** must keep the cookie and show Retry; Retry must succeed once the backend answers.
- **Valid cookie:** must render the dashboard and stay there (no redirect loop between `/` and `/dashboard`).
- **Logout while the backend is unreachable:** must still clear the cookie and navigate to `/`.

---

### Task 1: API base URL

**Files:**
- Create: `.env`
- Modify: `src/lib/api.tsx`

**Interfaces:**
- Produces: `API_URL: string` exported from `src/lib/api.tsx`; `api` axios instance whose `baseURL` is `API_URL`.

- [x] **Step 1:** Create `.env` with `VITE_API_URL=http://127.0.0.1:8001`.
- [x] **Step 2:** In `src/lib/api.tsx`, replace `baseURL: import.meta.env.VITE_API_URL` with a fallback:
  ```ts
  export const API_URL = import.meta.env.VITE_API_URL ?? "http://127.0.0.1:8001";
  ```
- [x] **Step 3:** Verify `npm run dev` still starts and the login request still reaches the backend.

### Task 2: Service functions

**Files:**
- Modify: `src/services/auth-api.tsx`

**Interfaces:**
- Produces: `me(): Promise<AuthUser>`, `logout(): Promise<void>`, `login(email, password): Promise<AuthUser>` (unchanged signature), `interface AuthUser`.
- `logout()` never throws and always calls `removeAuthToken()`.

- [x] **Step 1:** Change `login()` to post to the relative path `/api/user_login` (base URL now comes from the client).
- [x] **Step 2:** Add `me()` → `api.get<AuthUser>("/api/me")`, return `response.data`.
- [x] **Step 3:** Add `logout()` → `try { await api.post("/api/user_logout") } finally { removeAuthToken() }`, logging request failures so local state still clears.
- [x] **Step 4:** Verify `getAuthToken` is still used only by the interceptor and context.

### Task 3: Auth context

**Files:**
- Create: `src/lib/auth-context.tsx`
- Test: browser verification in Task 6

**Interfaces:**
- Produces: `AuthProvider`, `useAuth(): { status, user, signIn, signOut, refresh }`, `type AuthStatus = "checking" | "authenticated" | "anonymous" | "error"`.
- Consumes: `me`, `logout` from `@/services/auth-api`; `getAuthToken`, `removeAuthToken` from `@/lib/auth-cookie`.

- [x] **Step 1:** Initial state: `getAuthToken() ? "checking" : "anonymous"`.
- [x] **Step 2:** `refresh()`: no cookie → anonymous; otherwise `checking` → `me()` → `authenticated`; `401` → `removeAuthToken()` + anonymous; anything else → `error` (cookie untouched).
- [x] **Step 3:** Run `refresh()` once on mount via `useEffect`.
- [x] **Step 4:** `signIn(user)` sets the user and `authenticated`; `signOut()` awaits `logout()` then clears the user and sets `anonymous`.
- [x] **Step 5:** `useAuth()` throws if used outside the provider.

### Task 4: Status screen

**Files:**
- Create: `src/components/auth/auth-status-screen.tsx`

**Interfaces:**
- Produces: `AuthStatusScreen()` — reads `useAuth()`. `checking` → "Checking your session…"; `error` → "Can't reach the server." + Retry button calling `refresh()`.

- [x] **Step 1:** Implement the two states; use existing Tailwind utility classes for layout.

### Task 5: Guards and wiring

**Files:**
- Modify: `src/lib/protected-routes.tsx`
- Modify: `src/lib/public-routes.tsx`
- Modify: `src/main.tsx`
- Modify: `src/components/auth/sign-in.tsx`
- Modify: `src/components/dashboard/logout.tsx`

**Interfaces:**
- Consumes: `useAuth`, `AuthStatusScreen`.
- Produces: `PublicRoute` default export (renamed from the misnamed `ProtectedRoute`) in `public-routes.tsx`.

- [x] **Step 1:** `ProtectedRoute`: `checking|error` → `<AuthStatusScreen />`; `anonymous` → `<Navigate to="/" replace state={{ from: location }} />`; else `<Outlet />`.
- [x] **Step 2:** `PublicRoute`: `checking|error` → `<AuthStatusScreen />`; `authenticated` → `<Navigate to="/dashboard" replace />`; else `<Outlet />`.
- [x] **Step 3:** Wrap the router in `<AuthProvider>` in `src/main.tsx`.
- [x] **Step 4:** In `LoginForm`, call `signIn(user)` before `navigate("/dashboard")`.
- [x] **Step 5:** `LogoutButton` uses `signOut()` then `navigate("/", { replace: true })`.

### Task 6: Verification

- [x] **Step 1:** `npx tsc -p tsconfig.app.json --noEmit --ignoreDeprecations 6.0` → no errors in touched files.
- [x] **Step 2:** Browser, no cookie: `/dashboard` → login page, no `/api/me` request.
- [x] **Step 3:** Browser, `auth_token=garbage`: `/dashboard` → `/api/me` 401 → cookie removed → login.
- [x] **Step 4:** Browser, valid token (backend intercepted to return 200): `/dashboard` renders and stays; `/` redirects to `/dashboard`.
- [x] **Step 5:** Browser, `/api/me` 500: retry screen, cookie still present; Retry after the backend recovers → dashboard.
- [x] **Step 6:** Browser, logout with the backend unreachable: cookie removed, lands on `/`.
