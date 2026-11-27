<?php

namespace App\Domain\Sales\Payments;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PaymentManager
{
    /** @var array<string, PaymentGateway> */
    private array $drivers = [];

    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= $this->defaultDriver();

        return $this->drivers[$name] ??= $this->make($name);
    }

    /**
     * iyzico is the default, but without sandbox keys the local fake gateway is used so
     * the platform still works end to end out of the box.
     */
    public function defaultDriver(): string
    {
        $driver = (string) config('payments.driver');

        if ($driver === 'iyzico' && (config('payments.iyzico.api_key') === null || config('payments.iyzico.api_key') === '')) {
            Log::notice('No iyzico sandbox keys configured, using the fake payment gateway.');

            return 'fake';
        }

        return $driver;
    }

    private function make(string $name): PaymentGateway
    {
        return match ($name) {
            'iyzico' => new IyzicoGateway(
                (string) config('payments.iyzico.api_key'),
                (string) config('payments.iyzico.secret_key'),
                (string) config('payments.iyzico.base_url'),
            ),
            'fake' => new FakeGateway((string) config('app.key'), (string) config('payments.frontend_url')),
            default => throw new InvalidArgumentException("Unknown payment driver [{$name}]."),
        };
    }
}
