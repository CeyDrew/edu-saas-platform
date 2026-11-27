<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;

/**
 * Brands are managed by platform administrators only, who pass every check via
 * Gate::before. Everyone else is denied here.
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return false;
    }
}
