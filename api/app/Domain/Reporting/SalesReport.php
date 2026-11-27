<?php

namespace App\Domain\Reporting;

use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\SubscriptionStatus;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sales analytics of the current brand. Aggregates run in SQL (one pass per block)
 * and are cached per brand for a few minutes.
 */
class SalesReport
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @return array{
     *     period: array{from: string, to: string, days: int},
     *     totals: array{revenue: int, orders: int, average_order: int, discounts: int, platform_fee: int, net: int, active_subscriptions: int, mrr: int},
     *     daily: list<array{date: string, revenue: int, orders: int}>,
     *     top_courses: list<array{course: string, revenue: int, orders: int}>
     * }
     */
    public function build(int $days): array
    {
        return Cache::remember($this->tenants->prefix("sales-report:{$days}"), now()->addMinutes(5), function () use ($days) {
            $from = now()->subDays($days - 1)->startOfDay();
            $paid = Order::query()->where('status', OrderStatus::Paid)->where('paid_at', '>=', $from);

            $totals = (clone $paid)->selectRaw('coalesce(sum(total),0) revenue, count(*) orders, coalesce(sum(discount),0) discounts, coalesce(sum(platform_fee),0) fee')->first();
            $revenue = (int) $totals?->getAttribute('revenue');
            $orders = (int) $totals?->getAttribute('orders');
            $fee = (int) $totals?->getAttribute('fee');

            $byDay = (clone $paid)
                ->selectRaw('date(paid_at) day, sum(total) revenue, count(*) orders')
                ->groupBy('day')
                ->get()
                ->keyBy(fn ($row) => (string) $row->getAttribute('day'));

            $daily = [];
            for ($date = $from->copy(); $date->lte(now()); $date->addDay()) {
                $row = $byDay->get($date->toDateString());
                $daily[] = [
                    'date' => $date->toDateString(),
                    'revenue' => (int) $row?->getAttribute('revenue'),
                    'orders' => (int) $row?->getAttribute('orders'),
                ];
            }

            $topCourses = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('courses', 'courses.id', '=', 'order_items.course_id')
                ->where('orders.tenant_id', $this->tenants->id())
                ->where('orders.status', OrderStatus::Paid->value)
                ->where('orders.paid_at', '>=', $from)
                ->selectRaw('courses.title course, sum(orders.total) revenue, count(*) orders')
                ->groupBy('courses.title')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['course' => (string) $row->course, 'revenue' => (int) $row->revenue, 'orders' => (int) $row->orders])
                ->all();

            $active = Subscription::query()->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])->with('plan')->get();

            return [
                'period' => ['from' => $from->toDateString(), 'to' => now()->toDateString(), 'days' => $days],
                'totals' => [
                    'revenue' => $revenue,
                    'orders' => $orders,
                    'average_order' => $orders > 0 ? intdiv($revenue, $orders) : 0,
                    'discounts' => (int) $totals?->getAttribute('discounts'),
                    'platform_fee' => $fee,
                    'net' => $revenue - $fee,
                    'active_subscriptions' => $active->count(),
                    'mrr' => (int) $active->sum(fn (Subscription $s) => intdiv($s->plan->price, max(1, $s->plan->interval_months))),
                ],
                'daily' => $daily,
                'top_courses' => array_values($topCourses),
            ];
        });
    }

    /**
     * Revenue and commission per brand for the platform owner (no tenant context).
     *
     * @return array<int, array{slug: string, name: string, is_active: bool, revenue: int, platform_fee: int, orders: int, students: int}>
     */
    public function platform(int $days): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $sales = Order::acrossTenants()
            ->where('status', OrderStatus::Paid)
            ->where('paid_at', '>=', $from)
            ->selectRaw('tenant_id, sum(total) revenue, sum(platform_fee) fee, count(*) orders')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        $students = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('roles.name', 'student')
            ->selectRaw('users.tenant_id, count(*) students')
            ->groupBy('users.tenant_id')
            ->pluck('students', 'tenant_id');

        return Tenant::query()->orderBy('name')->get()->map(fn (Tenant $tenant) => [
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'is_active' => $tenant->is_active,
            'revenue' => (int) $sales->get($tenant->id)?->getAttribute('revenue'),
            'platform_fee' => (int) $sales->get($tenant->id)?->getAttribute('fee'),
            'orders' => (int) $sales->get($tenant->id)?->getAttribute('orders'),
            'students' => (int) ($students[$tenant->id] ?? 0),
        ])->values()->all();
    }
}
