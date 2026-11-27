<?php

namespace App\Http\Controllers\Api\Sales;

use App\Domain\Catalog\Models\Course;
use App\Domain\Sales\Models\SubscriptionPlan;
use App\Domain\Sales\Services\CheckoutService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function quote(CheckoutRequest $request): JsonResponse
    {
        $quote = $this->checkout->quote($this->product($request), $request->validated('coupon'));

        return response()->json(['data' => [
            'description' => $quote['description'],
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'coupon' => $quote['coupon']?->code,
        ]]);
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        ['order' => $order, 'session' => $session] = $this->checkout->start(
            $user,
            $this->product($request),
            $request->validated('coupon'),
            $request->billing(),
        );

        return response()->json([
            'data' => [
                'order' => new OrderResource($order->load('items')),
                'payment_page_url' => $session?->paymentPageUrl,
                'checkout_form_content' => $session?->checkoutFormContent,
            ],
        ], 201);
    }

    private function product(CheckoutRequest $request): Course|SubscriptionPlan
    {
        if ($request->filled('course')) {
            return Course::query()->published()->where('slug', $request->string('course'))->firstOrFail();
        }

        return SubscriptionPlan::query()->where('is_active', true)->findOrFail($request->integer('plan_id'));
    }
}
