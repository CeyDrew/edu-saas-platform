<?php

namespace App\Domain\Tenancy\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the tenant named by the request to the container. An unknown or inactive
 * brand is a 404 rather than a silent fallback to platform scope.
 */
class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->resolver->slugFrom($request);

        if ($slug !== null) {
            $tenant = $this->resolver->find($slug);

            abort_if($tenant === null, 404, 'Brand not found.');

            $this->context->set($tenant);
        }

        return $next($request);
    }
}
