<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AsyncOnlyController;
use App\Http\Controllers\Api\CancelResponseController;
use App\Http\Controllers\Api\CreateEmbeddingController;
use App\Http\Controllers\Api\CreateModerationController;
use App\Http\Controllers\Api\CreateResponseController;
use App\Http\Controllers\Api\DeleteResponseController;
use App\Http\Controllers\Api\ListModelsController;
use App\Http\Controllers\Api\ListResponsesController;
use App\Http\Controllers\Api\QueueChatCompletionController;
use App\Http\Controllers\Api\ShowModelController;
use App\Http\Controllers\Api\ShowResponseController;
use Illuminate\Support\Facades\Route;

/*
 | No auth middleware: this app only queues. Whatever Authorization the caller
 | sent is replayed upstream, and the upstream decides whether it is any good.
 */

/*
 | Queueing is rate limited per caller key: an entry costs a row and a worker
 | slot before upstream has seen it. Reading stays unthrottled — polling is the
 | documented way to collect an answer.
 */

$v1 = function (): void {
    Route::middleware('throttle:queue')->group(function () {
        Route::post('/v1/chat/completions', QueueChatCompletionController::class)->name('chat');
        Route::post('/v1/responses', CreateResponseController::class)->name('responses.create');

        // Synchronous like everything else in this block below, but rate
        // limited like a queued write: each call still costs the upstream
        // real compute, unlike the plain reads under ListModelsController.
        Route::post('/v1/embeddings', CreateEmbeddingController::class)->name('embeddings');
        Route::post('/v1/moderations', CreateModerationController::class)->name('moderations');
    });

    // Synchronous, unlike everything else under /v1: see ListModelsController.
    Route::get('/v1/models', ListModelsController::class)->name('models.index');
    Route::get('/v1/models/{id}', ShowModelController::class)->where('id', '.*')->name('models.show');

    Route::get('/v1/responses', ListResponsesController::class)->name('responses.index');
    Route::get('/v1/responses/{id}', ShowResponseController::class)->name('responses.show');
    Route::post('/v1/responses/{id}/cancel', CancelResponseController::class)->name('responses.cancel');
    Route::delete('/v1/responses/{id}', DeleteResponseController::class)->name('responses.destroy');

    Route::any('/v1/{any}', AsyncOnlyController::class)->where('any', '.*')->name('async-only');
};

/*
 | The same API twice: once at the root, where `gateway` middleware resolves
 | the default upstream, and once behind a gateway id.
 |
 | The whole group is mirrored rather than just the two POSTs. An SDK is
 | configured with one base_url — https://host/<id>/v1 — and then polls
 | /<id>/v1/responses/{id} off the same root, so a prefix that only accepted
 | writes would break collecting the answer.
 |
 | The uuid constraint is what keeps this wildcard from swallowing /up and the
 | dashboard's own paths.
 */

Route::middleware('gateway')->group($v1);

Route::prefix('{gateway}')
    ->whereUuid('gateway')
    ->middleware('gateway')
    ->name('gateway.')
    ->group($v1);
