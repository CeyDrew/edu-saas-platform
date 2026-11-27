<?php

namespace App\Http\Resources;

use App\Domain\Sales\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'currency' => $this->currency,
            'payment_provider' => $this->payment_provider,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'description' => $item->description,
                'price' => $item->price,
                'course_id' => $item->course_id,
                'plan_id' => $item->plan_id,
            ])),
            'customer' => $this->whenLoaded('user', fn () => ['name' => $this->user->name, 'email' => $this->user->email]),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? ['number' => $this->invoice->number, 'kind' => $this->invoice->kind] : null),
        ];
    }
}
