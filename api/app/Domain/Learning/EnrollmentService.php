<?php

namespace App\Domain\Learning;

use App\Domain\Catalog\Models\Course;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Sales\Models\Subscription;
use App\Models\User;

class EnrollmentService
{
    /**
     * Idempotent: enrolling twice (e.g. a repeated payment notification) keeps one row.
     */
    public function enroll(User $user, Course $course, string $source, ?int $orderId = null): Enrollment
    {
        return Enrollment::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['source' => $source, 'order_id' => $orderId],
        );
    }

    public function isEnrolled(User $user, Course $course): bool
    {
        return Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->exists();
    }

    /**
     * A student can study a course they bought (or got for free), or any subscription
     * course while their subscription grants access. Staff of the course always can.
     */
    public function canAccess(User $user, Course $course): bool
    {
        if ($user->tenant_id !== $course->tenant_id) {
            return false;
        }

        if ($user->can('update', $course) || $this->isEnrolled($user, $course)) {
            return true;
        }

        return $course->included_in_subscription && $this->activeSubscription($user) !== null;
    }

    public function activeSubscription(User $user): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->first(fn (Subscription $subscription) => $subscription->grantsAccess());
    }
}
