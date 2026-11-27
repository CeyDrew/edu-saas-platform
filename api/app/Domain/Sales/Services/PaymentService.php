<?php

namespace App\Domain\Sales\Services;

use App\Domain\Learning\EnrollmentService;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\OrderType;
use App\Domain\Sales\Events\OrderPaid;
use App\Domain\Sales\Events\PaymentFailed;
use App\Domain\Sales\Models\Coupon;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\WebhookEvent;
use App\Domain\Sales\Payments\PaymentManager;
use App\Domain\Sales\Payments\PaymentResult;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Turns provider results into paid orders. Every entry point (browser callback,
 * webhook, retry) ends in markPaid(), which is idempotent.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly EnrollmentService $enrollments,
        private readonly SubscriptionService $subscriptions,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @return string What happened: processed, duplicate or ignored.
     */
    public function handleWebhook(string $provider, Request $request): string
    {
        $notification = $this->payments->driver($provider)->parseWebhook($request);

        try {
            $event = WebhookEvent::query()->create([
                'provider' => $provider,
                'idempotency_key' => $notification->idempotencyKey,
                'payload' => $notification->payload,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Providers retry deliveries; the first one already did the work.
            return 'duplicate';
        }

        $order = $this->confirm($provider, $notification->token);
        $result = $order === null ? 'ignored' : 'processed';

        $event->forceFill(['result' => $result, 'processed_at' => now()])->save();

        return $result;
    }

    /**
     * Ask the provider for the result of a checkout and apply it to the order.
     */
    public function confirm(string $provider, string $token): ?Order
    {
        // Notifications arrive without a brand, so the order is looked up across tenants
        // and the rest runs inside its tenant.
        $order = Order::acrossTenants()->where('payment_token', $token)->where('payment_provider', $provider)->first();

        if ($order === null) {
            return null;
        }

        $tenant = Tenant::query()->findOrFail($order->tenant_id);

        return $this->tenants->run($tenant, function () use ($provider, $token, $order) {
            $result = $this->payments->driver($provider)->retrieve($token);

            $result->successful ? $this->markPaid($order, $result) : $this->markFailed($order, $result);

            return $order->refresh();
        });
    }

    public function markPaid(Order $order, ?PaymentResult $result): void
    {
        $paid = DB::transaction(function () use ($order, $result) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OrderStatus::Paid) {
                return false;
            }

            if ($result !== null && $result->paidAmount !== $locked->total) {
                throw new \RuntimeException("Paid amount {$result->paidAmount} does not match order {$locked->number}.");
            }

            $tenant = Tenant::query()->findOrFail($locked->tenant_id);
            $locked->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'platform_fee' => (int) round($locked->total * ((float) $tenant->commission_rate) / 100),
            ])->save();

            if ($result !== null) {
                $locked->payments()->create([
                    'provider' => $locked->payment_provider ?? 'free',
                    'provider_payment_id' => $result->paymentId,
                    'status' => 'success',
                    'amount' => $result->paidAmount,
                    'payload' => $result->raw,
                ]);
            }

            if ($locked->coupon_id !== null) {
                Coupon::query()->whereKey($locked->coupon_id)->increment('used_count');
            }

            $this->fulfill($locked, $result);

            return true;
        });

        if ($paid) {
            OrderPaid::dispatch($order->refresh());
        }
    }

    public function markFailed(Order $order, PaymentResult $result): void
    {
        if ($order->status !== OrderStatus::Pending) {
            return;
        }

        $order->forceFill(['status' => OrderStatus::Failed])->save();
        $order->payments()->create([
            'provider' => (string) $order->payment_provider,
            'provider_payment_id' => $result->paymentId,
            'status' => 'failure',
            'amount' => $order->total,
            'payload' => $result->raw + ['reason' => $result->failureReason],
        ]);

        PaymentFailed::dispatch($order, $result->failureReason);
    }

    private function fulfill(Order $order, ?PaymentResult $result): void
    {
        $order->load(['items.course', 'items.plan', 'user']);

        foreach ($order->items as $item) {
            if ($item->course !== null) {
                $this->enrollments->enroll($order->user, $item->course, $order->total === 0 ? 'free' : 'purchase', $order->id);
            }

            if ($item->plan !== null && $order->type === OrderType::Subscription) {
                $this->subscriptions->activate($order->user, $item->plan, $result?->cardToken);
            }
        }

        if ($order->type === OrderType::Renewal) {
            $this->subscriptions->renewalPaid($order);
        }
    }
}
