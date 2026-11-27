<?php

namespace App\Domain\Sales\Invoicing;

use App\Domain\Sales\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development driver: no integrator is called, the invoice is only logged.
 */
class LogEInvoiceProvider implements EInvoiceProvider
{
    public function issue(Order $order, string $kind, string $number): string
    {
        $ettn = (string) Str::uuid();
        Log::info("[e-invoice] {$kind} {$number} for order {$order->number}", ['ettn' => $ettn, 'billing' => $order->billing]);

        return $ettn;
    }
}
