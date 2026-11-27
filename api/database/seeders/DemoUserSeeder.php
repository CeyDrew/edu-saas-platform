<?php

namespace Database\Seeders;

use App\Domain\Identity\Role;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Predictable demo accounts (password: "password") plus synthetic students.
 */
class DemoUserSeeder extends Seeder
{
    public function run(TenantContext $context): void
    {
        User::factory()->platform()->create([
            'name' => 'Platform Yöneticisi',
            'email' => 'admin@edusaas.test',
        ])->assignRole(Role::PlatformAdmin->value);

        Tenant::query()->each(function (Tenant $tenant) use ($context) {
            $context->run($tenant, function () use ($tenant) {
                User::factory()->create([
                    'name' => $tenant->name.' Yöneticisi',
                    'email' => "yonetici@{$tenant->slug}.test",
                ])->assignRole(Role::BrandAdmin->value);

                User::factory()->create([
                    'name' => fake()->name(),
                    'email' => "egitmen@{$tenant->slug}.test",
                ])->assignRole(Role::Instructor->value);

                User::factory()->count(2)->create()->each->assignRole(Role::Instructor->value);

                User::factory()->create([
                    'name' => fake()->name(),
                    'email' => "ogrenci@{$tenant->slug}.test",
                ])->assignRole(Role::Student->value);

                User::factory()->count(25)->create()->each->assignRole(Role::Student->value);
            });
        });
    }
}
