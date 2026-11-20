<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\SiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('regions', [RegionController::class, 'index']);
        Route::post('regions', [RegionController::class, 'store']);

        Route::get('sites', [SiteController::class, 'index']);
        Route::post('sites', [SiteController::class, 'store']);
        Route::get('sites/{site}', [SiteController::class, 'show']);
        Route::put('sites/{site}', [SiteController::class, 'update']);
        Route::delete('sites/{site}', [SiteController::class, 'destroy']);
        Route::post('sites/{site}/engineers/{user}', [SiteController::class, 'assignEngineer']);
        Route::delete('sites/{site}/engineers/{user}', [SiteController::class, 'removeEngineer']);
    });
});
