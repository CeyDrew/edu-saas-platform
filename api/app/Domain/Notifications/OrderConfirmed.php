<?php

namespace App\Domain\Notifications;

use App\Domain\Sales\Models\Order;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $brand = Tenant::query()->findOrFail($this->order->tenant_id);
        $items = $this->order->items()->pluck('description')->implode(', ');

        return (new MailMessage)
            ->subject("{$brand->name}: siparişin onaylandı ({$this->order->number})")
            ->greeting("Merhaba {$notifiable->name},")
            ->line("Ödemen alındı: {$items}.")
            ->line('Toplam: '.number_format($this->order->total / 100, 2, ',', '.').' TL')
            ->action('Kurslarıma git', str_replace('{tenant}', $brand->slug, (string) config('payments.frontend_url')).'/panel')
            ->salutation("{$brand->name} ekibi");
    }

    public function toSms(User $notifiable): SmsMessage
    {
        return new SmsMessage("Siparişin {$this->order->number} onaylandı. İyi çalışmalar!");
    }
}
