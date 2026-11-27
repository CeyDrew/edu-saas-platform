<?php

use App\Domain\Tenancy\Models\Tenant;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'kodakademi', 'name' => 'Kod Akademi']);
});

it('resolves the tenant from the X-Tenant header', function () {
    $this->getJson('/api/tenant', ['X-Tenant' => 'kodakademi'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Kod Akademi');
});

it('resolves the tenant from the subdomain', function () {
    $this->getJson('http://kodakademi.localhost/api/tenant')
        ->assertOk()
        ->assertJsonPath('data.slug', 'kodakademi');
});

it('resolves the tenant from the SPA origin', function () {
    $this->getJson('/api/tenant', ['Origin' => 'http://kodakademi.localhost:3000'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'kodakademi');
});

it('rejects unknown brands', function () {
    $this->getJson('/api/tenant', ['X-Tenant' => 'yok-boyle-marka'])->assertNotFound();
});

it('rejects closed brands, even after they were cached', function () {
    $this->getJson('/api/tenant', ['X-Tenant' => 'kodakademi'])->assertOk();

    $this->tenant->update(['is_active' => false]);

    $this->getJson('/api/tenant', ['X-Tenant' => 'kodakademi'])->assertNotFound();
});

it('requires a tenant on brand endpoints', function () {
    $this->getJson('/api/tenant')->assertStatus(400);
});

it('ignores reserved subdomains', function () {
    $this->getJson('http://admin.localhost/api/tenant')->assertStatus(400);
});
