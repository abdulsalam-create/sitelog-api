<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\VisitReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('auth/login', 'login')->middleware('throttle:5,1');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::post('auth/logout', 'logout');
            Route::get('auth/me', 'me');
        });

        Route::controller(RegionController::class)->group(function () {
            Route::get('regions', 'index');
            Route::post('regions', 'store');
        });

        Route::controller(SiteController::class)->group(function () {
            Route::get('sites', 'index');
            Route::post('sites', 'store');
            Route::get('sites/{site}', 'show');
            Route::put('sites/{site}', 'update');
            Route::delete('sites/{site}', 'destroy');
            Route::post('sites/{site}/engineers/{user}', 'assignEngineer');
            Route::delete('sites/{site}/engineers/{user}', 'removeEngineer');
        });

        Route::controller(VisitReportController::class)->group(function () {
            Route::get('sites/{site}/reports', 'indexForSite');
            Route::post('sites/{site}/reports', 'store');

            Route::get('reports', 'index');
            Route::get('reports/{report}', 'show');
            Route::put('reports/{report}', 'update');
            Route::delete('reports/{report}', 'destroy');
            Route::post('reports/{report}/submit', 'submit');
            Route::post('reports/{report}/approve', 'approve');
            Route::post('reports/{report}/reject', 'reject');
        });
    });
});
