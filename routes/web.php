<?php

declare(strict_types=1);

use App\Http\Controllers\Web\CreateGatewayController;
use App\Http\Controllers\Web\DestroyGatewayController;
use App\Http\Controllers\Web\EditGatewayController;
use App\Http\Controllers\Web\ListGatewaysController;
use App\Http\Controllers\Web\MakeGatewayDefaultController;
use App\Http\Controllers\Web\ShowBacklogController;
use App\Http\Controllers\Web\ShowBacklogEntryController;
use App\Http\Controllers\Web\StoreGatewayController;
use App\Http\Controllers\Web\UpdateGatewayController;
use Illuminate\Support\Facades\Route;

/*
 | Guarded by DASHBOARD_TOKEN: these pages render whole prompts and answers,
 | which no caller key entitles anyone to see. The same token guards the
 | gateway table, which decides where every request in the app is sent.
 */

Route::middleware('dashboard')->group(function () {
    Route::get('/', ShowBacklogController::class)->name('backlog');
    Route::get('/entries/{id}', ShowBacklogEntryController::class)->name('backlog.entry');

    Route::get('/gateways', ListGatewaysController::class)->name('gateways');
    Route::get('/gateways/create', CreateGatewayController::class)->name('gateways.create');
    Route::post('/gateways', StoreGatewayController::class)->name('gateways.store');
    Route::get('/gateways/{gateway}/edit', EditGatewayController::class)->name('gateways.edit');
    Route::put('/gateways/{gateway}', UpdateGatewayController::class)->name('gateways.update');
    Route::delete('/gateways/{gateway}', DestroyGatewayController::class)->name('gateways.destroy');
    Route::post('/gateways/{gateway}/default', MakeGatewayDefaultController::class)->name('gateways.default');
});
