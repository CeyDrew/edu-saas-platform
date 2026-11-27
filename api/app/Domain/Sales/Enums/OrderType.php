<?php

namespace App\Domain\Sales\Enums;

enum OrderType: string
{
    case Course = 'course';
    case Subscription = 'subscription';
    case Renewal = 'renewal';
}
