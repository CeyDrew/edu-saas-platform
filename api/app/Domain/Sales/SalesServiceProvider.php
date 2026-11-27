<?php

namespace App\Domain\Sales;

use App\Domain\Notifications\LogSmsSender;
use App\Domain\Notifications\OrderConfirmed;
use App\Domain\Notifications\SmsSender;
use App\Domain\Notifications\SubscriptionSuspendedNotice;
use App\Domain\Sales\Events\OrderPaid;
use App\Domain\Sales\Events\SubscriptionSuspended;
use App\Domain\Sales\Invoicing\EInvoiceProvider;
use App\Domain\Sales\Invoicing\LogEInvoiceProvider;
use App\Domain\Sales\Jobs\IssueInvoice;
use App\Domain\Sales\Payments\PaymentManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);
        $this->app->bind(SmsSender::class, LogSmsSender::class);
        $this->app->bind(EInvoiceProvider::class, LogEInvoiceProvider::class);
    }

    public function boot(): void
    {
        Event::listen(OrderPaid::class, function (OrderPaid $event): void {
            $event->order->user->notify(new OrderConfirmed($event->order));
            IssueInvoice::dispatch($event->order->id);
        });

        Event::listen(SubscriptionSuspended::class, function (SubscriptionSuspended $event): void {
            $event->subscription->user->notify(new SubscriptionSuspendedNotice($event->subscription));
        });
    }
}
