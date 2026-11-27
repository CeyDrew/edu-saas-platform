# ADR 0002: Laravel API + separate Next.js frontend

## Status
Accepted

## Context
The product has two very different faces:

- **Storefronts**: public, SEO-critical pages per brand (titles, Open Graph, JSON-LD, sitemaps),
  themed per brand and served on the brand's subdomain.
- **Panels**: interactive dashboards for students, instructors, brand admins and the platform
  owner (player, quizzes, editors, charts).

Options considered: Laravel + Inertia (React), Laravel + Livewire, or a Laravel API with a
separate Next.js app.

## Decision
A Laravel REST API (`api/`) and a Next.js App Router app (`web/`).

- Storefront pages are React Server Components rendered per request; the brand comes from the
  subdomain, which the Next.js proxy (`src/proxy.ts`) turns into a `/[tenant]` route segment.
- Panels are client components using TanStack Query against the same API.
- The browser talks only to its own origin: the proxy forwards `/api/*` and `/sanctum/*` to
  Laravel with the brand in `X-Tenant` (and drops any client-sent value). Sanctum session
  cookies therefore stay first-party per brand subdomain, and no CORS setup is needed.
- Server components call the API directly over the internal network.

## Consequences
- One API serves the web app, tokens for other clients, and the OpenAPI docs.
- Two deployables and two toolchains instead of one; types are duplicated in TypeScript
  (`web/src/lib/api/types.ts`).
- Inertia would have avoided the API layer, but per-brand SSR/SEO on subdomains and a public
  API were requirements, which fit a separate SSR frontend better. Livewire was ruled out for
  the media-heavy, highly interactive player and dashboards.
