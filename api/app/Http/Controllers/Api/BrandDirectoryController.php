<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tenancy\Models\Tenant;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public list of active brands for the platform landing page.
 */
class BrandDirectoryController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return TenantResource::collection(Tenant::query()->where('is_active', true)->orderBy('name')->get());
    }
}
