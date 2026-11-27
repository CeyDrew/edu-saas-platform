# ADR 0006: Carrying tenant context into queued jobs

## Status
Accepted

## Context
Jobs (video processing, AI question generation, notifications, subscription renewal) run in a
Horizon worker, outside the HTTP request that resolved the tenant. Without a tenant the global
scope is a no-op, so a job would silently read every brand's data.

## Decision
Make tenant propagation automatic instead of relying on each job to remember it:

- `Queue::createPayloadUsing()` adds `tenant_id` to every job payload at dispatch time.
- A `JobProcessing` listener restores that tenant into `TenantContext` before `handle()` runs.
- `JobProcessed` / `JobExceptionOccurred` restore the previous context, which keeps the `sync`
  driver (jobs running inside a request) correct as well.
- `TenantContext` is a scoped singleton, so the worker resets it between jobs.

## Consequences
- Job classes stay free of tenancy plumbing.
- Jobs dispatched from platform code carry `tenant_id: null` and stay unscoped on purpose.
- Covered by `QueueTenantContextTest`, which dispatches to the database queue, clears the context
  and runs a real worker.
