<?php

namespace App\Http\Controllers\Api\Brand;

use App\Domain\Identity\Role;
use App\Domain\Sales\Enums\CouponType;
use App\Domain\Sales\Models\Coupon;
use App\Domain\Sales\Models\Order;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\User;
use CeyDrew\TrValidation\Rules\Iban;
use CeyDrew\TrValidation\Rules\Vkn;
use CeyDrew\TrValidation\Tr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Orders, coupons and payout details of the current brand (brand admins only).
 */
class SalesController extends Controller
{
    public function orders(Request $request): AnonymousResourceCollection
    {
        $this->authorizeAdmin($request);

        $request->validate(['status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'refunded'])]]);

        return OrderResource::collection(
            Order::query()
                ->with(['items', 'user', 'invoice'])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->latest('id')
                ->paginate(25),
        );
    }

    public function coupons(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        return response()->json(['data' => Coupon::query()->latest('id')->get()]);
    }

    public function storeCoupon(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'code' => ['required', 'alpha_dash:ascii', 'max:40', Rule::unique('coupons')->where('tenant_id', $context->id())],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'integer', 'min:1', Rule::when($request->input('type') === 'percent', 'max:100')],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'min_amount' => ['nullable', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $coupon = Coupon::query()->create(['code' => Str::upper($data['code'])] + $data);

        return response()->json(['data' => $coupon], 201);
    }

    public function updateCoupon(Request $request, Coupon $coupon): JsonResponse
    {
        $this->authorizeAdmin($request);

        $coupon->update($request->validate(['is_active' => ['required', 'boolean']]));

        return response()->json(['data' => $coupon]);
    }

    /**
     * Bank account the platform pays the brand's revenue share into.
     */
    public function payout(Request $request, TenantContext $context): JsonResponse
    {
        $this->authorizeAdmin($request);
        $tenant = $context->get();
        abort_if($tenant === null, 400);

        if ($request->isMethod('GET')) {
            return response()->json(['data' => $tenant->payout]);
        }

        $data = $request->validate([
            'account_holder' => ['required', 'string', 'max:150'],
            'iban' => ['required', new Iban],
            'vkn' => ['required', new Vkn],
        ]);
        $data['iban'] = Tr::formatIban($data['iban']);

        $tenant->forceFill(['payout' => $data])->save();

        return response()->json(['data' => $tenant->payout]);
    }

    private function authorizeAdmin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole(Role::BrandAdmin->value), 403);
    }
}
