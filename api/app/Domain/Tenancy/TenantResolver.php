<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Finds the tenant slug in a request: the X-Tenant header wins, then the subdomain
 * of the Host (marka.localhost), then the subdomain of the Origin (SPA on :3000).
 */
class TenantResolver
{
    public function __construct(private readonly string $baseDomain) {}

    public function slugFrom(Request $request): ?string
    {
        $header = $request->headers->get('X-Tenant');

        if (is_string($header) && $header !== '') {
            return strtolower($header);
        }

        $origin = $request->headers->get('Origin');
        $originHost = is_string($origin) ? parse_url($origin, PHP_URL_HOST) : null;

        foreach ([$request->getHost(), $originHost] as $host) {
            if (is_string($host) && ($slug = $this->subdomain($host)) !== null) {
                return $slug;
            }
        }

        return null;
    }

    public function subdomain(string $host): ?string
    {
        $suffix = '.'.$this->baseDomain;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));

        if ($label === '' || str_contains($label, '.') || in_array($label, Tenant::RESERVED_SLUGS, true)) {
            return null;
        }

        return $label;
    }

    public function find(string $slug): ?Tenant
    {
        $id = Cache::remember("tenant-slug:{$slug}", now()->addMinutes(10), fn () => Tenant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->value('id') ?? 0);

        return $id ? Tenant::query()->whereKey($id)->first() : null;
    }
}
