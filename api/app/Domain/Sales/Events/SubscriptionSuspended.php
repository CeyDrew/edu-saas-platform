<?php

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionSuspended
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Subscription $subscription) {}
}
