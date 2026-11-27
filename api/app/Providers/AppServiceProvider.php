<?php

namespace App\Providers;

use App\Domain\Identity\Role;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Platform administrators may do anything a policy allows to anyone.
        Gate::before(fn (User $user) => $user->hasRole(Role::PlatformAdmin->value) ? true : null);

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip().'|'.$request->input('email')),
        ]);

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // The player sends a heartbeat every ~15 seconds.
        RateLimiter::for('progress', fn (Request $request) => Limit::perMinute(30)->by((string) $request->user()?->getAuthIdentifier()));

        // Model calls are expensive: limited per brand, not per user.
        RateLimiter::for('ai', fn (Request $request) => Limit::perHour((int) config('ai.hourly_limit'))
            ->by('tenant:'.app(TenantContext::class)->id()));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
