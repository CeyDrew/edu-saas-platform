<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Brand\MemberController;
use App\Http\Controllers\Api\Catalog\CourseController as CatalogCourseController;
use App\Http\Controllers\Api\CurrentTenantController;
use App\Http\Controllers\Api\Manage\CourseController as ManageCourseController;
use App\Http\Controllers\Api\Manage\LessonController;
use App\Http\Controllers\Api\Manage\SectionController;
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

    Route::get('/courses', [CatalogCourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/{course}', [CatalogCourseController::class, 'show'])->name('courses.show');
    Route::get('/categories', [CatalogCourseController::class, 'categories']);

    Route::middleware(['auth:sanctum', 'member'])->group(function () {
        Route::prefix('brand')->group(function () {
            Route::get('/members', [MemberController::class, 'index']);
            Route::post('/members', [MemberController::class, 'store']);
        });

        Route::prefix('manage')->name('manage.')->group(function () {
            Route::get('/courses', [ManageCourseController::class, 'index'])->name('courses.index');
            Route::post('/courses', [ManageCourseController::class, 'store']);
            Route::get('/courses/{course:id}', [ManageCourseController::class, 'show'])->name('courses.show');
            Route::patch('/courses/{course:id}', [ManageCourseController::class, 'update']);
            Route::delete('/courses/{course:id}', [ManageCourseController::class, 'destroy']);
            Route::post('/courses/{course:id}/publish', [ManageCourseController::class, 'publish']);
            Route::post('/courses/{course:id}/unpublish', [ManageCourseController::class, 'unpublish']);
            Route::post('/courses/{course:id}/cover', [ManageCourseController::class, 'cover']);

            Route::post('/courses/{course:id}/sections', [SectionController::class, 'store']);
            Route::put('/courses/{course:id}/sections/order', [SectionController::class, 'reorder']);
            Route::patch('/sections/{section}', [SectionController::class, 'update']);
            Route::delete('/sections/{section}', [SectionController::class, 'destroy']);

            Route::post('/sections/{section}/lessons', [LessonController::class, 'store']);
            Route::patch('/lessons/{lesson}', [LessonController::class, 'update']);
            Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);
            Route::post('/lessons/{lesson}/video', [LessonController::class, 'video']);
        });
    });
});

// Platform owner endpoints; only reachable without a brand context.
Route::middleware(['auth:sanctum', 'platform'])->prefix('platform')->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::patch('/tenants/{tenant}', [TenantController::class, 'update']);
});
