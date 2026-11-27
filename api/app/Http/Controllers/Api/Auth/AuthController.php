<?php

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Identity\Role;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Session login for the Next.js SPA (Sanctum stateful cookies) and personal access
 * tokens for API clients. Both are tenant aware: on a brand host only that brand's
 * users can sign in, on the platform host only platform administrators can.
 */
class AuthController extends Controller
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'email', 'phone', 'password']));
        $user->assignRole(Role::Student->value);

        $this->startSession($request, $user);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        $user = $this->findByCredentials($request->string('email')->toString(), $request->string('password')->toString());

        $this->startSession($request, $user);

        return new UserResource($user);
    }

    /**
     * Issue a personal access token for non-browser clients.
     */
    public function token(LoginRequest $request): JsonResponse
    {
        $user = $this->findByCredentials($request->string('email')->toString(), $request->string('password')->toString());

        $token = $user->createToken($request->string('device_name', 'api')->toString(), $user->getRoleNames()->all());

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('tenant'));
    }

    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        // SPA sessions carry a TransientToken; only bearer requests have a stored token.
        if ($request->bearerToken() !== null) {
            $user->currentAccessToken()->delete();
        } else {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->noContent();
    }

    private function findByCredentials(string $email, string $password): User
    {
        $user = User::query()
            ->where('email', $email)
            // Without a brand only platform users exist; the tenant scope covers the other case.
            ->when(! $this->tenants->check(), fn (Builder $query) => $query->whereNull('tenant_id'))
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        return $user;
    }

    private function startSession(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
    }
}
