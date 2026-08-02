<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Upstream\Upstreams;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Retrieves one model by id.
 *
 * There is no per-protocol "get one model" call: Ollama has nothing that
 * answers by name the way OpenAI and Anthropic do, so rather than give it a
 * driver method the other two fake, this reads the same list
 * `ListModelsController` does and picks the matching entry out of it —
 * one lookup that works identically for every protocol.
 *
 * It reads that list as an array rather than as a rendered response. Encoding
 * every model a gateway serves into JSON, decoding it again and returning one
 * element of it is three passes over the whole list to answer with a single
 * entry, and a LiteLLM instance can list hundreds. Reusing
 * `ListModelsController::for()` also means this endpoint shares the caller's
 * cached listing instead of asking the upstream a second time.
 */
class ShowModelController
{
    public function __invoke(Request $request, string $id, Upstreams $upstreams): JsonResponse
    {
        $listing = ListModelsController::for($request, $upstreams);

        /* An upstream error is already an answer, with its own status: pass it on rather than reporting the model missing. */
        if ($listing instanceof JsonResponse) {
            return $listing;
        }

        /** @var array{data?: array<int, array<string, mixed>>} $listing */
        return self::find($listing, $id) ?? self::notFound($id);
    }

    /** @param  array{data?: array<int, array<string, mixed>>}  $listing */
    private static function find(array $listing, string $id): ?JsonResponse
    {
        foreach ($listing['data'] ?? [] as $model) {
            if (($model['id'] ?? null) === $id) {
                return response()->json($model);
            }
        }

        return null;
    }

    private static function notFound(string $id): JsonResponse
    {
        return response()->json([
            'error' => [
                'message' => "The model '{$id}' does not exist for this gateway.",
                'type' => 'model_not_found',
            ],
        ], 404);
    }
}
