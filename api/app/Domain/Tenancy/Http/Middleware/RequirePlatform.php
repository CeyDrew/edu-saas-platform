<?php

namespace App\Domain\Tenancy\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform endpoints must not run inside a brand, otherwise the tenant scope would
 * silently narrow platform-wide reports to one brand.
 */
class RequirePlatform
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->context->check(), 404);

        return $next($request);
    }
}
