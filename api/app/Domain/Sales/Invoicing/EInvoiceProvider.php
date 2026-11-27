<?php

namespace App\Domain\Sales\Invoicing;

use App\Domain\Sales\Models\Order;

/**
 * E-Arşiv / e-Fatura integrator (e.g. a GİB-approved private integrator).
 */
interface EInvoiceProvider
{
    /**
     * Issue the invoice and return the integrator reference (UUID / ETTN).
     */
    public function issue(Order $order, string $kind, string $number): string;
}
