<?php

namespace App\Policies;

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Role;
use App\Models\User;

class CoursePolicy
{
    /**
     * Opening the course management panel.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([Role::BrandAdmin->value, Role::Instructor->value]);
    }

    public function view(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Instructors manage their own courses; brand admins manage every course of the brand.
     */
    public function update(User $user, Course $course): bool
    {
        if ($user->tenant_id !== $course->tenant_id) {
            return false;
        }

        return $user->hasRole(Role::BrandAdmin->value)
            || ($user->hasRole(Role::Instructor->value) && $course->instructor_id === $user->id);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course) && ! $course->isPublished();
    }

    public function publish(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }
}
