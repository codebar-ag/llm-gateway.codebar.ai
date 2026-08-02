<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\BacklogEntryResource;
use App\Services\Backlog;
use App\Services\QueueEstimate;
use App\Support\ChatPayload;
use App\Support\ToolCalls;
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

    public function __invoke(Request $request, Backlog $backlog, QueueEstimate $estimate): JsonResponse
    {
        $entry = $backlog->add($request, $this->toChatRequest($request->json()->all()));

        // Estimated after the insert, so the entry counts itself and the
        // caller is told the line they are in rather than the one before it.
        return (new BacklogEntryResource($entry))
            ->withPlace($estimate->for($entry))
            ->response($request)
            ->setStatusCode(202);
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
            ->merge($this->tools($payload))
            ->prepend(ChatPayload::toMessages($payload), 'messages')
            ->prepend(Arr::get($payload, 'model'), 'model')
            ->all();
    }

    /**
     * Tools are not a rename but a reshape, so they sit outside the map: the
     * Responses API states a function flat and names a forced one at the top
     * level, and chat completions nests both under `function`.
     *
     * A payload that declared no function tool adds neither key. A bare
     * `tool_choice` with nothing to choose from is not a request an upstream
     * can honour, and forwarding it would turn a caller's own mistake into an
     * upstream error they cannot read.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function tools(array $payload): array
    {
        $tools = ToolCalls::tools(Arr::get($payload, 'tools'));

        if ($tools === []) {
            return [];
        }

        return array_filter([
            'tools' => $tools,
            'tool_choice' => ToolCalls::choice(Arr::get($payload, 'tool_choice')),
        ], fn (mixed $value) => $value !== null);
    }
}
