<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\BacklogEntryResource;
use App\Services\Backlog;
use App\Support\ChatPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * The only endpoint that rewrites the body, and the reason this app exists:
 * LiteLLM cannot serve /v1/responses from an Ollama backend, so the payload
 * is translated into a chat completion. Everything else is passed through.
 */
class CreateResponseController
{
    /** Responses API parameter → chat completions parameter. */
    private const PARAMETER_MAP = [
        'max_output_tokens' => 'max_tokens',
        'temperature' => 'temperature',
        'top_p' => 'top_p',
        'response_format' => 'response_format',
    ];

    public function __invoke(Request $request, Backlog $backlog): JsonResponse
    {
        $entry = $backlog->add($request, $this->toChatRequest($request->json()->all()));

        return (new BacklogEntryResource($entry))->response($request)->setStatusCode(202);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function toChatRequest(array $payload): array
    {
        return collect(self::PARAMETER_MAP)
            ->filter(fn (string $to, string $from) => array_key_exists($from, $payload))
            ->mapWithKeys(fn (string $to, string $from) => [$to => $payload[$from]])
            ->prepend(ChatPayload::toMessages($payload), 'messages')
            ->prepend(Arr::get($payload, 'model'), 'model')
            ->all();
    }
}
