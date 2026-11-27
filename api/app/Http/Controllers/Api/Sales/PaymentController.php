<?php

namespace App\Http\Controllers\Api\Sales;

use App\Domain\Sales\Models\Order;
use App\Domain\Sales\Payments\FakeGateway;
use App\Domain\Sales\Payments\InvalidWebhookSignature;
use App\Domain\Sales\Payments\PaymentManager;
use App\Domain\Sales\Services\PaymentService;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Server-to-server notification. Signature checked, then processed exactly once.
     */
    public function webhook(Request $request, string $provider): JsonResponse
    {
        abort_unless(in_array($provider, ['iyzico', 'fake'], true), 404);

        try {
            $result = $this->payments->handleWebhook($provider, $request);
        } catch (InvalidWebhookSignature) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return response()->json(['result' => $result]);
    }

    /**
     * iyzico posts the buyer back here with the checkout token after the payment page.
     */
    public function iyzicoCallback(Request $request): RedirectResponse
    {
        $token = $request->string('token')->toString();
        $order = $token !== '' ? $this->payments->confirm('iyzico', $token) : null;

        abort_if($order === null, 404);

        $tenant = Tenant::query()->findOrFail($order->tenant_id);
        $origin = str_replace('{tenant}', $tenant->slug, (string) config('payments.frontend_url'));

        return redirect()->away("{$origin}/odeme/sonuc?siparis={$order->number}");
    }

    /**
     * The local payment page: records the outcome and sends the signed notification
     * through the same webhook path a real provider would use.
     */
    public function fakeComplete(Request $request, PaymentManager $manager): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string'], 'success' => ['required', 'boolean']]);

        /** @var User $user */
        $user = $request->user();
        $order = Order::query()->where('payment_token', $data['token'])->where('user_id', $user->id)->firstOrFail();

        $gateway = $manager->driver('fake');
        abort_unless($gateway instanceof FakeGateway, 404);

        $this->payments->handleWebhook('fake', $gateway->complete($data['token'], (bool) $data['success']));

        return response()->json(['data' => new OrderResource($order->refresh()->load('items'))]);
    }
}
