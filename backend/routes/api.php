<?php

use App\Http\Controllers\Api\V1\ClientController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('internal.api')
    ->group(function (): void {
        Route::apiResource('clients', ClientController::class)
            ->only(['index', 'store', 'show', 'update']);
    });
