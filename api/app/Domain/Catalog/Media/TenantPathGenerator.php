<?php

namespace App\Domain\Catalog\Media;

use App\Domain\Tenancy\TenantContext;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Stores every file under tenant{id}/, so brand files never share a directory.
 */
class TenantPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->base($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->base($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->base($media).'/responsive/';
    }

    private function base(Media $media): string
    {
        $model = $media->model;
        $tenantId = $model?->getAttribute('tenant_id') ?? app(TenantContext::class)->id() ?? 0;

        return "tenant{$tenantId}/{$media->getKey()}";
    }
}
