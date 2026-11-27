<?php

namespace Tests\Support;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Discovers every Eloquent model that uses BelongsToTenant, so a new model is
 * covered by the isolation test the moment it opts into tenancy.
 */
final class TenantModels
{
    /**
     * @return list<class-string<Model>>
     */
    public static function all(): array
    {
        $models = [];

        foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/app')->name('*.php') as $file) {
            $class = 'App\\'.Str::of($file->getRelativePathname())->replace(['/', '.php'], ['\\', ''])->toString();

            if (class_exists($class)
                && is_subclass_of($class, Model::class)
                && in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $models[] = $class;
            }
        }

        sort($models);

        return $models;
    }
}
