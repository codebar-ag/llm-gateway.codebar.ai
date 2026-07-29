<?php

declare(strict_types=1);

use App\Http\Controllers\Web\CreateGatewayController;
use App\Http\Controllers\Web\DestroyCallerLabelController;
use App\Http\Controllers\Web\DestroyGatewayController;
use App\Http\Controllers\Web\DestroySessionController;
use App\Http\Controllers\Web\DownloadEntryPartController;
use App\Http\Controllers\Web\EditGatewayController;
use App\Http\Controllers\Web\ListCallersController;
use App\Http\Controllers\Web\ListGatewaysController;
use App\Http\Controllers\Web\MakeGatewayDefaultController;
use App\Http\Controllers\Web\ShowBacklogController;
use App\Http\Controllers\Web\ShowBacklogEntryController;
use App\Http\Controllers\Web\ShowLoginController;
use App\Http\Controllers\Web\StoreCallerLabelController;
use App\Http\Controllers\Web\StoreGatewayController;
use App\Http\Controllers\Web\StoreSessionController;
use App\Http\Controllers\Web\UpdateGatewayController;
use Illuminate\Support\Facades\Route;

/*
 | Two guards, in this order.
 |
 | `dashboard` is the shared DASHBOARD_TOKEN. It is optional: leave it empty
 | and it steps aside. Set it and it stands in front of everything below,
 | including the login form — so a visitor without the token is refused before
 | learning that an account is what gets them further.
 |
 | `auth` is the operator's own session, and it is never optional. These pages
 | render whole prompts and answers, which no caller key entitles anyone to
 | see, and the gateway table decides where every request in the app is sent.
 |
 | The login routes sit inside `dashboard` but outside `auth`, or there would
 | be nowhere to log in.
 */

Route::middleware('dashboard')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', ShowLoginController::class)->name('login');
        Route::post('/login', StoreSessionController::class)->name('login.store');
    });

    Route::post('/logout', DestroySessionController::class)->middleware('auth')->name('logout');
});

Route::middleware(['dashboard', 'auth'])->group(function () {
    Route::get('/', ShowBacklogController::class)->name('backlog');
    Route::get('/entries/{id}', ShowBacklogEntryController::class)->name('backlog.entry');
    Route::get('/entries/{id}/download/{part}', DownloadEntryPartController::class)
        ->whereIn('part', ['request', 'response', 'answer', 'error'])
        ->name('backlog.entry.download');

    Route::get('/callers', ListCallersController::class)->name('callers');
    Route::post('/callers', StoreCallerLabelController::class)->name('callers.store');
    Route::delete('/callers/{hash}', DestroyCallerLabelController::class)->name('callers.destroy');

    Route::get('/gateways', ListGatewaysController::class)->name('gateways');
    Route::get('/gateways/create', CreateGatewayController::class)->name('gateways.create');
    Route::post('/gateways', StoreGatewayController::class)->name('gateways.store');
    Route::get('/gateways/{gateway}/edit', EditGatewayController::class)->name('gateways.edit');
    Route::put('/gateways/{gateway}', UpdateGatewayController::class)->name('gateways.update');
    Route::delete('/gateways/{gateway}', DestroyGatewayController::class)->name('gateways.destroy');
    Route::post('/gateways/{gateway}/default', MakeGatewayDefaultController::class)->name('gateways.default');
});
