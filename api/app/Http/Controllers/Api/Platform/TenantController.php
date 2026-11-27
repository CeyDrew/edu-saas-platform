<?php

namespace App\Http\Controllers\Api\Platform;

use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\TenantRequest;
use App\Http\Resources\PlatformTenantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TenantController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tenant::class);

        return PlatformTenantResource::collection(
            Tenant::query()->withCount('users')->orderBy('name')->paginate(25),
        );
    }

    public function store(TenantRequest $request): JsonResponse
    {
        Gate::authorize('create', Tenant::class);

        $tenant = Tenant::query()->create($request->validated());

        return (new PlatformTenantResource($tenant))->response()->setStatusCode(201);
    }

    public function update(TenantRequest $request, Tenant $tenant): PlatformTenantResource
    {
        Gate::authorize('update', $tenant);

        $tenant->update($request->validated());

        return new PlatformTenantResource($tenant);
    }
}
