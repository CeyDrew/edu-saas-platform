<?php

namespace App\Domain\Sales\Services;

use App\Domain\Catalog\Models\Course;
use App\Domain\Learning\EnrollmentService;
use App\Domain\Sales\Enums\OrderType;
use App\Domain\Sales\Models\Coupon;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\SubscriptionPlan;
use App\Domain\Sales\Payments\CheckoutSession;
use App\Domain\Sales\Payments\PaymentManager;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly EnrollmentService $enrollments,
    ) {}

    /**
     * Price breakdown for a course or plan, with an optional coupon.
     *
     * @return array{subtotal: int, discount: int, total: int, coupon: Coupon|null, description: string}
     */
    public function quote(Course|SubscriptionPlan $product, ?string $couponCode): array
    {
        $subtotal = $product->price;
        $coupon = null;
        $discount = 0;

        if ($couponCode !== null && $couponCode !== '') {
            $coupon = Coupon::query()->where('code', Str::upper(trim($couponCode)))->first();
            $reason = $coupon === null ? 'Kupon bulunamadı.' : $coupon->rejectionReason($subtotal);

            if ($reason !== null) {
                throw ValidationException::withMessages(['coupon' => $reason]);
            }

            $discount = $coupon?->discountFor($subtotal) ?? 0;
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $subtotal - $discount,
            'coupon' => $coupon,
            'description' => $product instanceof Course ? $product->title : $product->name,
        ];
    }

    /**
     * Create a pending order and open a hosted checkout for it. Free courses (or a 100%
     * coupon) skip the payment provider entirely.
     *
     * @param  array<string, string|null>  $billing
     * @return array{order: Order, session: CheckoutSession|null}
     */
    public function start(User $buyer, Course|SubscriptionPlan $product, ?string $couponCode, array $billing): array
    {
        if ($product instanceof Course && $this->enrollments->isEnrolled($buyer, $product)) {
            throw ValidationException::withMessages(['course' => 'Bu kursa zaten kayıtlısın.']);
        }

        $quote = $this->quote($product, $couponCode);
        $gateway = $this->payments->driver();

        $order = DB::transaction(function () use ($buyer, $product, $quote, $billing, $gateway) {
            $order = Order::query()->create([
                'user_id' => $buyer->id,
                'number' => $this->orderNumber(),
                'type' => $product instanceof Course ? OrderType::Course : OrderType::Subscription,
                'subtotal' => $quote['subtotal'],
                'discount' => $quote['discount'],
                'total' => $quote['total'],
                'coupon_id' => $quote['coupon']?->id,
                'billing' => $billing,
                'payment_provider' => $gateway->name(),
            ]);

            $order->items()->create([
                'course_id' => $product instanceof Course ? $product->id : null,
                'plan_id' => $product instanceof SubscriptionPlan ? $product->id : null,
                'description' => $quote['description'],
                'price' => $quote['subtotal'],
            ]);

            return $order;
        });

        if ($order->total === 0) {
            app(PaymentService::class)->markPaid($order, null);

            return ['order' => $order->refresh(), 'session' => null];
        }

        $session = $gateway->initialize($order->load('items'), $buyer, (string) config('payments.callback_url'));
        $order->forceFill(['payment_token' => $session->token])->save();

        return ['order' => $order, 'session' => $session];
    }

    public function orderNumber(): string
    {
        return 'S'.now()->format('ymd').Str::upper(Str::random(8));
    }
}
