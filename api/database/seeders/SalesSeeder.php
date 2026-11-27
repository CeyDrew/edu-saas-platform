<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Course;
use App\Domain\Identity\Role;
use App\Domain\Learning\Models\Enrollment;
use App\Domain\Sales\Enums\OrderStatus;
use App\Domain\Sales\Enums\OrderType;
use App\Domain\Sales\Models\Coupon;
use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Models\SubscriptionPlan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Plans, coupons and roughly three months of synthetic paid orders so the student
 * panel and the sales reports have data.
 */
class SalesSeeder extends Seeder
{
    public function run(TenantContext $context): void
    {
        mt_srand(42);

        Tenant::query()->each(function (Tenant $tenant) use ($context) {
            $context->run($tenant, function () use ($tenant) {
                SubscriptionPlan::query()->create([
                    'name' => 'Aylık Tüm Erişim',
                    'description' => 'Abonelik kapsamındaki tüm kurslar, her ay yenilenir.',
                    'price' => $tenant->slug === 'zirvedershane' ? 24900 : 19900,
                    'interval_months' => 1,
                ]);

                Coupon::query()->create(['code' => 'HOSGELDIN20', 'type' => 'percent', 'value' => 20]);
                Coupon::query()->create(['code' => 'YUZTL', 'type' => 'fixed', 'value' => 10000, 'min_amount' => 30000, 'max_uses' => 50]);

                $this->orders($tenant);
            });
        });
    }

    private function orders(Tenant $tenant): void
    {
        $courses = Course::query()->where('price', '>', 0)->get();
        $students = User::query()->role(Role::Student->value)->get();

        foreach ($students as $i => $student) {
            // The demo student owns the first course so the learning panel is not empty.
            $owned = $student->email === "ogrenci@{$tenant->slug}.test"
                ? $courses->take(2)
                : $courses->random(mt_rand(0, min(2, $courses->count())));

            foreach ($owned as $course) {
                $paidAt = Carbon::now()->subDays(mt_rand(0, 89))->setTime(mt_rand(8, 23), mt_rand(0, 59));
                $discount = mt_rand(0, 4) === 0 ? intdiv($course->price, 5) : 0;
                $total = $course->price - $discount;

                $order = new Order([
                    'user_id' => $student->id,
                    'number' => 'S'.$paidAt->format('ymd').Str::upper(Str::random(8)),
                    'type' => OrderType::Course,
                    'subtotal' => $course->price,
                    'discount' => $discount,
                    'total' => $total,
                    'billing' => ['type' => 'individual', 'name' => $student->name, 'city' => 'İstanbul'],
                    'payment_provider' => 'fake',
                ]);
                $order->forceFill([
                    'status' => OrderStatus::Paid,
                    'paid_at' => $paidAt,
                    'platform_fee' => (int) round($total * ((float) $tenant->commission_rate) / 100),
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ])->save();

                $order->items()->create(['course_id' => $course->id, 'description' => $course->title, 'price' => $course->price]);
                $order->payments()->create(['provider' => 'fake', 'provider_payment_id' => 'seed_'.$order->number, 'status' => 'success', 'amount' => $total]);

                Enrollment::query()->firstOrCreate(
                    ['user_id' => $student->id, 'course_id' => $course->id],
                    ['source' => 'purchase', 'order_id' => $order->id, 'created_at' => $paidAt],
                );
            }
        }
    }
}
