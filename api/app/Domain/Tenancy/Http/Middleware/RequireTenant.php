<?php

namespace App\Domain\Tenancy\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->context->check(), 400, 'This endpoint needs a brand (X-Tenant header or subdomain).');

        return $next($request);
    }
}
