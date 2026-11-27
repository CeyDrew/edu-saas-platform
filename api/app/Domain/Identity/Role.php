<?php

namespace App\Domain\Identity;

enum Role: string
{
    case PlatformAdmin = 'platform-admin';
    case BrandAdmin = 'brand-admin';
    case Instructor = 'instructor';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::PlatformAdmin => 'Platform yöneticisi',
            self::BrandAdmin => 'Marka yöneticisi',
            self::Instructor => 'Eğitmen',
            self::Student => 'Öğrenci',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
