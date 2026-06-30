<?php

use App\Http\Controllers\Api\V1\ClientBrandController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ClientExtractionConfigController;
use App\Http\Controllers\Api\V1\ClientOverviewController;
use App\Http\Controllers\Api\V1\ClientPostController;
use App\Http\Controllers\Api\V1\ClientUsageLedgerController;
use App\Http\Controllers\Api\V1\PlatformController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('internal.api')
    ->group(function (): void {
        Route::get('platforms', PlatformController::class)
            ->name('platforms.index');

        Route::apiResource('clients', ClientController::class)
            ->only(['index', 'store', 'show', 'update']);

        Route::scopeBindings()->prefix('clients/{client}')->group(function (): void {
            Route::get('overview', ClientOverviewController::class)
                ->name('clients.overview');

            Route::apiResource('brands', ClientBrandController::class)
                ->only(['index', 'store', 'show', 'update']);

            Route::apiResource('extraction-configs', ClientExtractionConfigController::class)
                ->parameters(['extraction-configs' => 'extractionConfig'])
                ->only(['index', 'store', 'show', 'update']);

            Route::apiResource('posts', ClientPostController::class)
                ->only(['index', 'show']);

            Route::get('usage-ledger', ClientUsageLedgerController::class)
                ->name('clients.usage-ledger.index');
        });
    });
