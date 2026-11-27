<?php

namespace App\Domain\Tenancy\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defence in depth: user lookups are already tenant scoped, but a token or session
 * must never act on a brand its user does not belong to.
 */
class EnsureTenantMember
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->tenant_id === $this->context->id(), 403);

        return $next($request);
    }
}
