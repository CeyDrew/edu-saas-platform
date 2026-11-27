<?php

namespace App\Domain\Notifications;

use App\Domain\Sales\Models\Subscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspendedNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Subscription $subscription)
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
        return (new MailMessage)
            ->subject('Aboneliğin askıya alındı')
            ->line('Yenileme ödemesi üç kez alınamadığı için aboneliğin askıya alındı.')
            ->line('Kart bilgini güncelleyip yeniden abone olarak erişimini geri açabilirsin.');
    }

    public function toSms(User $notifiable): SmsMessage
    {
        return new SmsMessage('Abonelik ödemen alınamadı, erişimin askıya alındı.');
    }
}
