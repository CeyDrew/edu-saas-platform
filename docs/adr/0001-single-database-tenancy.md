# ADR 0001: Single database with `tenant_id`

## Status
Accepted

## Context
The platform hosts many small and medium brands. Each brand has its own courses, students,
orders and theme, while the platform owner needs cross-brand reports (sales, commission).

Options considered:

1. **Database per tenant** – strongest isolation, but every migration runs N times, connection
   management gets complex, and platform-wide reports need cross-database queries.
2. **Schema per tenant** – similar trade-offs, and MySQL has no real schemas.
3. **Single database, `tenant_id` column** – one migration path, cheap tenants, simple reporting.
   Isolation is enforced in the application layer.

## Decision
Use a single database. Every tenant-owned table has an indexed `tenant_id` foreign key.

- `ResolveTenant` middleware resolves the brand from the `X-Tenant` header, the Host subdomain
  (`kodakademi.localhost`) or the SPA `Origin`, and binds it to `TenantContext` (a scoped singleton).
- The `BelongsToTenant` trait adds `TenantScope` (a global scope) and fills `tenant_id` on create.
- Unknown or inactive brands return 404 instead of falling back to platform scope.
- Unique constraints include `tenant_id` (e.g. `users (tenant_id, email)`), so one person can
  join several brands with the same e-mail.
- Cache keys, search indexes and media paths are prefixed with the tenant id.

## Consequences
- A missed scope would leak data, so `TenantIsolationTest` discovers every model that uses the
  trait and proves it cannot read another tenant's rows. New models are covered automatically.
- Platform code that must read across tenants calls `Model::acrossTenants()`, which makes the
  intent explicit and easy to grep for.
- Global scopes do not apply to raw `DB::table()` queries; those are avoided in tenant code.
