<?php

use App\Domain\Identity\Role;
use App\Domain\Tenancy\Models\Tenant;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi']);
    $this->other = Tenant::factory()->create(['slug' => 'dilokulu']);
    $this->headers = ['X-Tenant' => 'kodakademi'];
});

describe('platform panel', function () {
    it('lets the platform admin list and open brands', function () {
        Sanctum::actingAs(userWithRole(Role::PlatformAdmin));

        $this->getJson('/api/platform/tenants')->assertOk()->assertJsonCount(2, 'data');

        $this->postJson('/api/platform/tenants', [
            'slug' => 'yeni-marka', 'name' => 'Yeni Marka', 'theme' => ['primary' => '#112233'],
        ])->assertCreated()->assertJsonPath('data.slug', 'yeni-marka');
    });

    it('lets the platform admin close a brand', function () {
        Sanctum::actingAs(userWithRole(Role::PlatformAdmin));

        $this->patchJson('/api/platform/tenants/kodakademi', ['is_active' => false])->assertOk();

        $this->getJson('/api/tenant', $this->headers)->assertNotFound();
    });

    it('rejects reserved and malformed slugs', function (string $slug) {
        Sanctum::actingAs(userWithRole(Role::PlatformAdmin));

        $this->postJson('/api/platform/tenants', ['slug' => $slug, 'name' => 'X'])->assertJsonValidationErrors('slug');
    })->with(['admin', 'Büyük Harf', 'kodakademi', '-tire']);

    it('is closed to every brand role', function (Role $role) {
        Sanctum::actingAs(userWithRole($role, $this->brand));

        $this->getJson('/api/platform/tenants')->assertForbidden();
    })->with([Role::BrandAdmin, Role::Instructor, Role::Student]);

    it('is not reachable from a brand host', function () {
        Sanctum::actingAs(userWithRole(Role::PlatformAdmin));

        $this->getJson('/api/platform/tenants', $this->headers)->assertNotFound();
    });
});

describe('brand members', function () {
    it('lets a brand admin list and add members of their brand only', function () {
        $admin = userWithRole(Role::BrandAdmin, $this->brand);
        userWithRole(Role::Student, $this->brand);
        userWithRole(Role::Student, $this->other);
        Sanctum::actingAs($admin);

        $this->getJson('/api/brand/members', $this->headers)->assertOk()->assertJsonCount(2, 'data');

        $this->postJson('/api/brand/members', [
            'name' => 'Yeni Eğitmen', 'email' => 'egitmen@example.com', 'password' => 'gizli-sifre', 'role' => 'instructor',
        ], $this->headers)->assertCreated()->assertJsonPath('data.roles', ['instructor']);

        $this->assertDatabaseHas('users', ['email' => 'egitmen@example.com', 'tenant_id' => $this->brand->id]);
    });

    it('never lets a brand admin hand out the platform role', function () {
        Sanctum::actingAs(userWithRole(Role::BrandAdmin, $this->brand));

        $this->postJson('/api/brand/members', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'gizli-sifre', 'role' => 'platform-admin',
        ], $this->headers)->assertJsonValidationErrors('role');
    });

    it('shows instructors only students', function () {
        userWithRole(Role::Student, $this->brand);
        userWithRole(Role::BrandAdmin, $this->brand);
        Sanctum::actingAs(userWithRole(Role::Instructor, $this->brand));

        $this->getJson('/api/brand/members?role=brand-admin', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.roles', ['student']);
    });

    it('forbids students and instructors from adding members', function (Role $role) {
        Sanctum::actingAs(userWithRole($role, $this->brand));

        $this->postJson('/api/brand/members', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'gizli-sifre', 'role' => 'student',
        ], $this->headers)->assertForbidden();
    })->with([Role::Instructor, Role::Student]);

    it('forbids students from listing members', function () {
        Sanctum::actingAs(userWithRole(Role::Student, $this->brand));

        $this->getJson('/api/brand/members', $this->headers)->assertForbidden();
    });

    it('forbids acting on another brand', function () {
        Sanctum::actingAs(userWithRole(Role::BrandAdmin, $this->other));

        $this->getJson('/api/brand/members', $this->headers)->assertForbidden();
    });
});
