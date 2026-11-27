<?php

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * Development SMS driver: writes messages to the log instead of an SMS provider.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::channel(config('services.sms.log_channel', 'stack'))->info("[SMS] {$phone}: {$message}");
    }
}
