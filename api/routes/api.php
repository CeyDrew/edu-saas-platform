<?php

use App\Http\Controllers\Api\CurrentTenantController;
use Illuminate\Support\Facades\Route;

Route::middleware('tenant')->group(function () {
    Route::get('/tenant', CurrentTenantController::class);
});
