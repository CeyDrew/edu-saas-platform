<?php

use App\Domain\Identity\Role;
use App\Domain\Tenancy\Models\Tenant;

beforeEach(function () {
    $this->brand = Tenant::factory()->create(['slug' => 'kodakademi']);
    $this->other = Tenant::factory()->create(['slug' => 'dilokulu']);
    $this->spa = ['Referer' => 'http://kodakademi.localhost:3000/giris', 'X-Tenant' => 'kodakademi'];
});

it('registers a student in the current brand and starts a session', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ayşe Yılmaz',
        'email' => 'ayse@example.com',
        'phone' => '0532 123 45 67',
        'password' => 'gizli-sifre',
        'password_confirmation' => 'gizli-sifre',
    ], $this->spa)
        ->assertCreated()
        ->assertJsonPath('data.roles', [Role::Student->value]);

    // Next request: drop the in-memory user, keep the session cookie.
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/auth/me', $this->spa)->assertOk()->assertJsonPath('data.email', 'ayse@example.com');
    $this->assertDatabaseHas('users', ['email' => 'ayse@example.com', 'tenant_id' => $this->brand->id]);
});

it('allows the same e-mail in two different brands', function () {
    userWithRole(Role::Student, $this->other, ['email' => 'ayse@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Ayşe', 'email' => 'ayse@example.com',
        'password' => 'gizli-sifre', 'password_confirmation' => 'gizli-sifre',
    ], $this->spa)->assertCreated();
});

it('validates Turkish mobile numbers on sign up', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ayşe', 'email' => 'ayse@example.com', 'phone' => '12345',
        'password' => 'gizli-sifre', 'password_confirmation' => 'gizli-sifre',
    ], $this->spa)->assertJsonValidationErrors('phone');
});

it('logs in with a session cookie on the brand host', function () {
    userWithRole(Role::Student, $this->brand, ['email' => 'ogrenci@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'ogrenci@example.com', 'password' => 'password'], $this->spa)
        ->assertOk();

    $this->assertAuthenticated('web');
});

it('does not let a user of another brand log in', function () {
    userWithRole(Role::Student, $this->other, ['email' => 'baska@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'baska@example.com', 'password' => 'password'], $this->spa)
        ->assertJsonValidationErrors('email');
});

it('only lets platform users log in without a brand', function () {
    userWithRole(Role::BrandAdmin, $this->brand, ['email' => 'yonetici@example.com']);
    userWithRole(Role::PlatformAdmin, null, ['email' => 'admin@example.com']);
    $platformSpa = ['Referer' => 'http://localhost:3000/'];

    $this->postJson('/api/auth/login', ['email' => 'yonetici@example.com', 'password' => 'password'], $platformSpa)
        ->assertJsonValidationErrors('email');
    $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'password'], $platformSpa)
        ->assertOk();
});

it('issues personal access tokens with role abilities', function () {
    userWithRole(Role::Instructor, $this->brand, ['email' => 'egitmen@example.com']);

    $token = $this->postJson('/api/auth/token', [
        'email' => 'egitmen@example.com', 'password' => 'password', 'device_name' => 'cli',
    ], ['X-Tenant' => 'kodakademi'])->assertCreated()->json('token');

    $this->getJson('/api/auth/me', ['X-Tenant' => 'kodakademi', 'Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.roles', [Role::Instructor->value]);
});

it('rejects a token on another brand', function () {
    $user = userWithRole(Role::BrandAdmin, $this->brand);
    $token = $user->createToken('cli')->plainTextToken;

    $this->getJson('/api/brand/members', ['X-Tenant' => 'dilokulu', 'Authorization' => "Bearer {$token}"])
        ->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    $user = userWithRole(Role::Student, $this->brand);
    $token = $user->createToken('cli')->plainTextToken;
    $headers = ['X-Tenant' => 'kodakademi', 'Authorization' => "Bearer {$token}"];

    $this->postJson('/api/auth/logout', [], $headers)->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('throttles repeated login attempts', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'yanlis'], $this->spa);
    }

    $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'yanlis'], $this->spa)
        ->assertTooManyRequests();
});
