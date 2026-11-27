<?php

namespace App\Policies;

use App\Domain\Identity\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Brand admins list their brand's members; instructors see students.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::BrandAdmin->value, Role::Instructor->value]);
    }

    public function view(User $user, User $member): bool
    {
        return $user->tenant_id === $member->tenant_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::BrandAdmin->value);
    }

    public function update(User $user, User $member): bool
    {
        return $user->tenant_id === $member->tenant_id && $user->hasRole(Role::BrandAdmin->value);
    }
}
