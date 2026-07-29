<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class AsyncOnlyController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'error' => [
                'message' => 'This endpoint only offers async processing: POST /v1/responses or POST /v1/chat/completions, results via GET /v1/responses/{id}.',
                'type' => 'async_only',
            ],
        ], 404);
    }
}
