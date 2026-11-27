<?php

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
