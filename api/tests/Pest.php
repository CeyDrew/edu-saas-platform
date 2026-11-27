<?php

use App\Domain\Identity\Role;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RoleSeeder::class))
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Create a user with a role inside a brand (or on the platform when $tenant is null).
 */
function userWithRole(Role $role, ?Tenant $tenant = null, array $attributes = []): User
{
    $factory = $tenant === null ? User::factory()->platform() : User::factory()->forTenant($tenant);

    return tap($factory->create($attributes))->assignRole($role->value);
}

/**
 * Run a callback as a brand, e.g. to create fixtures through tenant-scoped factories.
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function asTenant(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant, $callback);
}

/**
 * A real, tiny MP4 so media validation sees genuine video content. Needs ffmpeg.
 */
function fakeVideo(string $name = 'ders.mp4', int $seconds = 2): UploadedFile
{
    $path = sys_get_temp_dir().'/'.uniqid('video', true).'.mp4';
    $result = Process::run([
        'ffmpeg', '-y', '-loglevel', 'error', '-f', 'lavfi',
        '-i', "testsrc=duration={$seconds}:size=160x120:rate=5", '-pix_fmt', 'yuv420p', $path,
    ]);

    if ($result->failed()) {
        test()->markTestSkipped('ffmpeg is not installed.');
    }

    return new UploadedFile($path, $name, 'video/mp4', null, true);
}
