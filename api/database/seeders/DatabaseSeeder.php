<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TenantSeeder::class,
            DemoUserSeeder::class,
            CatalogSeeder::class,
            SalesSeeder::class,
            LearningSeeder::class,
            AiDemoSeeder::class,
        ]);
    }
}
