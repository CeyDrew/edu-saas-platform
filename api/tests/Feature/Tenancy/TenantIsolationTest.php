<?php

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\TenantModels;

dataset('tenant models', fn () => TenantModels::all());

it('discovers the tenant-owned models', function () {
    expect(TenantModels::all())->not->toBeEmpty();
});

it('never leaks rows of another tenant', function (string $model) {
    /** @var class-string<Model> $model */
    [$a, $b] = Tenant::factory()->count(2)->create();
    $context = app(TenantContext::class);

    $context->run($a, fn () => $model::factory()->count(2)->create());
    $foreign = $context->run($b, fn () => $model::factory()->count(3)->create())->first();

    $context->run($a, function () use ($model, $a, $foreign) {
        expect($model::query()->count())->toBeGreaterThan(0)
            ->and($model::query()->where('tenant_id', '!=', $a->id)->count())->toBe(0)
            ->and($model::query()->find($foreign->getKey()))->toBeNull()
            ->and($model::query()->whereKey($foreign->getKey())->exists())->toBeFalse();
    });
})->with('tenant models');

it('fills tenant_id from the current tenant when creating', function (string $model) {
    /** @var class-string<Model> $model */
    $tenant = Tenant::factory()->create();

    $row = app(TenantContext::class)->run($tenant, fn () => $model::factory()->create());

    expect($row->getAttribute('tenant_id'))->toBe($tenant->id);
})->with('tenant models');
