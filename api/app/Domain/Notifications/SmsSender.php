<?php

namespace App\Domain\Notifications;

interface SmsSender
{
    public function send(string $phone, string $message): void;
}
