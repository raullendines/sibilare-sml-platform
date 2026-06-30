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
    ->middleware('supabase.auth')
    ->group(function (): void {
        Route::get('platforms', PlatformController::class)
            ->name('platforms.index');

        Route::get('clients', [ClientController::class, 'index'])
            ->name('clients.index');

        Route::post('clients', [ClientController::class, 'store'])
            ->name('clients.store');

        Route::scopeBindings()->middleware('client.access')->prefix('clients/{client}')->group(function (): void {
            Route::get('', [ClientController::class, 'show'])
                ->name('clients.show');

            Route::match(['put', 'patch'], '', [ClientController::class, 'update'])
                ->name('clients.update');

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
