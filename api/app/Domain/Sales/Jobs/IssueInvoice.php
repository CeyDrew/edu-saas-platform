<?php

namespace App\Domain\Sales\Jobs;

use App\Domain\Sales\Invoicing\EInvoiceProvider;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IssueInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue('default');
    }

    public function handle(EInvoiceProvider $provider): void
    {
        $order = Order::query()->with('invoice')->findOrFail($this->orderId);

        if ($order->invoice !== null || $order->total === 0) {
            return;
        }

        // Companies (VKN) get an e-Fatura, individuals (TCKN) an e-Arşiv invoice.
        $kind = ($order->billing['type'] ?? 'individual') === 'corporate' ? 'e-fatura' : 'e-arsiv';
        $number = ($kind === 'e-fatura' ? 'EF' : 'EA').now()->format('Y').Str::padLeft((string) $order->id, 9, '0');

        Invoice::query()->create([
            'order_id' => $order->id,
            'number' => $number,
            'kind' => $kind,
            'provider_reference' => $provider->issue($order, $kind, $number),
            'total' => $order->total,
            'issued_at' => now(),
        ]);
    }
}
