<?php

namespace Database\Seeders;

use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Three demo brands with clearly different looks so the storefronts are easy to tell apart.
     */
    public const array BRANDS = [
        [
            'slug' => 'kodakademi',
            'name' => 'Kod Akademi',
            'tagline' => 'Sıfırdan yazılımcılığa, proje odaklı online eğitim.',
            'theme' => ['primary' => '#4f46e5', 'accent' => '#22d3ee', 'font' => 'sans', 'hero' => 'gradient'],
            'plan' => 'pro',
            'commission_rate' => 8,
        ],
        [
            'slug' => 'dilokulu',
            'name' => 'Lingua Dil Okulu',
            'tagline' => 'Konuşarak öğren: İngilizce, Almanca ve İspanyolca.',
            'theme' => ['primary' => '#e11d48', 'accent' => '#f59e0b', 'font' => 'serif', 'hero' => 'split'],
            'plan' => 'starter',
            'commission_rate' => 12,
        ],
        [
            'slug' => 'zirvedershane',
            'name' => 'Zirve Dershanesi',
            'tagline' => 'YKS ve LGS için video dersler, deneme sınavları ve kişisel tekrar.',
            'theme' => ['primary' => '#059669', 'accent' => '#84cc16', 'font' => 'rounded', 'hero' => 'stats'],
            'plan' => 'business',
            'commission_rate' => 6,
        ],
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $brand) {
            Tenant::query()->updateOrCreate(['slug' => $brand['slug']], $brand + ['is_active' => true]);
        }
    }
}
