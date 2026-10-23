# ERP Frontend

Next.js 16 App Router frontend for the ERP services. It includes a cookie-based
BFF for the Symfony authentication service and TailAdmin-style dashboard pages.

## Prerequisites

- Node.js 20+
- pnpm 12+
- The authentication service running at `http://127.0.0.1:8001`

## Setup

```bash
pnpm install
cp .env.example .env.local
pnpm dev
```

Open <http://localhost:3000>. Use `localhost` rather than `127.0.0.1` during
development so Next.js HMR is treated as same-origin.

`AUTH_SERVICE_URL` is server-only and defaults to `http://127.0.0.1:8001`.
Set it in `.env.local` when the auth service moves or the gateway is available.

## Commands

```bash
pnpm test      # Vitest unit and route-handler tests
pnpm lint      # ESLint
pnpm build     # Production build
pnpm start     # Serve the production build
```

## Auth notes

- The backend token lives only in the `HttpOnly` `auth_session` cookie. It is
  never returned to browser JavaScript.
- `proxy.ts` performs an optimistic cookie check for `/dashboard/:path*`; each
  dashboard page also calls `getCurrentUser()` so page data is not rendered for
  an invalid session.
- Product, client, inventory, and notification data is currently typed sample
  data. Only authentication talks to a live service.
- Password reset and email verification links are out of scope for this slice.
