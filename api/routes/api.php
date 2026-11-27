<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Brand\MemberController;
use App\Http\Controllers\Api\CurrentTenantController;
use App\Http\Controllers\Api\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/token', [AuthController::class, 'token']);
        Route::post('/register', [AuthController::class, 'register'])->middleware('tenant');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Brand scoped endpoints: storefront (public) and panels (authenticated).
Route::middleware('tenant')->group(function () {
    Route::get('/tenant', CurrentTenantController::class);

    Route::middleware(['auth:sanctum', 'member'])->prefix('brand')->group(function () {
        Route::get('/members', [MemberController::class, 'index']);
        Route::post('/members', [MemberController::class, 'store']);
    });
});

// Platform owner endpoints; only reachable without a brand context.
Route::middleware(['auth:sanctum', 'platform'])->prefix('platform')->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::patch('/tenants/{tenant}', [TenantController::class, 'update']);
});
