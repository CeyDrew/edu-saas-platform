<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;

class CurrentTenantController extends Controller
{
    public function __invoke(TenantContext $context): TenantResource
    {
        return new TenantResource($context->get());
    }
}
