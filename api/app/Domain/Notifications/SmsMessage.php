<?php

namespace App\Domain\Notifications;

final readonly class SmsMessage
{
    public function __construct(public string $text) {}
}
