<?php

namespace App\Domain\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Notification channel "sms": the notification builds the text with toSms().
 */
class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || $notifiable->phone === null || ! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (! $message instanceof SmsMessage) {
            throw new RuntimeException('toSms() must return an SmsMessage.');
        }

        $this->sender->send($notifiable->phone, $message->text);
    }
}
