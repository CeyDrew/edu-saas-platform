<?php

namespace App\Http\Controllers\Api\Sales;

use App\Domain\Learning\EnrollmentService;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Subscription;
use App\Domain\Sales\Models\SubscriptionPlan;
use App\Domain\Sales\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in student's purchases and subscription.
 */
class AccountController extends Controller
{
    public function orders(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()->where('user_id', $this->user($request)->id)->with(['items', 'invoice'])->latest('id')->paginate(20),
        );
    }

    public function order(Request $request, Order $order): OrderResource
    {
        abort_unless($order->user_id === $this->user($request)->id, 404);

        return new OrderResource($order->load(['items', 'invoice']));
    }

    public function subscription(Request $request, EnrollmentService $enrollments): JsonResponse
    {
        $subscription = Subscription::query()->with('plan')->where('user_id', $this->user($request)->id)->latest('id')->first();

        return response()->json(['data' => $subscription === null ? null : [
            'id' => $subscription->id,
            'plan' => $subscription->plan->name,
            'price' => $subscription->plan->price,
            'status' => $subscription->status->value,
            'current_period_end' => $subscription->current_period_end->toIso8601String(),
            'failed_attempts' => $subscription->failed_attempts,
            'grants_access' => $subscription->grantsAccess(),
        ]]);
    }

    public function cancelSubscription(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $subscription = Subscription::query()->where('user_id', $this->user($request)->id)->latest('id')->firstOrFail();
        $subscriptions->cancel($subscription);

        return response()->json(['data' => ['status' => $subscription->status->value]]);
    }

    public function plans(): JsonResponse
    {
        return response()->json([
            'data' => SubscriptionPlan::query()->where('is_active', true)->orderBy('price')->get(['id', 'name', 'description', 'price', 'interval_months']),
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
