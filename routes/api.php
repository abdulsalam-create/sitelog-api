<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\VisitReportController;
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

        Route::get('sites/{site}/reports', [VisitReportController::class, 'indexForSite']);
        Route::post('sites/{site}/reports', [VisitReportController::class, 'store']);

        Route::get('reports', [VisitReportController::class, 'index']);
        Route::get('reports/{report}', [VisitReportController::class, 'show']);
        Route::put('reports/{report}', [VisitReportController::class, 'update']);
        Route::delete('reports/{report}', [VisitReportController::class, 'destroy']);
        Route::post('reports/{report}/submit', [VisitReportController::class, 'submit']);
        Route::post('reports/{report}/approve', [VisitReportController::class, 'approve']);
        Route::post('reports/{report}/reject', [VisitReportController::class, 'reject']);
    });
});
