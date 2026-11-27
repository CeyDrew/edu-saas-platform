<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price')->comment('kuruş per period');
            $table->unsignedTinyInteger('interval_months')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('type', 10)->comment('percent|fixed');
            $table->unsignedInteger('value')->comment('percent or kuruş');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('min_amount')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32)->unique();
            $table->string('type', 20)->comment('course|subscription|renewal');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total');
            $table->unsignedInteger('platform_fee')->default(0)->comment('commission kept by the platform');
            $table->char('currency', 3)->default('TRY');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->json('billing')->nullable();
            $table->string('payment_provider', 20)->nullable();
            $table->string('payment_token')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'paid_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('price');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_payment_id')->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('amount');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_payment_id']);
        });

        // Every incoming notification is recorded once; a repeat delivery hits the unique key.
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('idempotency_key');
            $table->json('payload');
            $table->string('result', 20)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'idempotency_key']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('current_period_end');
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('card_token')->nullable()->comment('provider token of the stored card');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_period_end']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->comment('purchase|free|subscription');
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32)->unique();
            $table->string('kind', 20)->comment('e-arsiv|e-fatura');
            $table->string('provider_reference')->nullable();
            $table->unsignedInteger('total');
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['invoices', 'enrollments', 'subscriptions', 'webhook_events', 'payments', 'order_items', 'orders', 'coupons', 'subscription_plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
