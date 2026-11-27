<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Tenant;

/**
 * Holds the tenant the current request or job runs for.
 *
 * Bound as a scoped singleton so it is reset between requests and queue jobs.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Run a callback as the given tenant, restoring the previous context afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(?Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }

    /**
     * Prefix a cache key, file path or search index with the current tenant.
     */
    public function prefix(string $key, string $separator = ':'): string
    {
        return 'tenant'.($this->id() ?? '0').$separator.$key;
    }
}
