<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Models\Gateway;
use App\Support\ChatPayload;
use App\Support\JsonValue;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Ollama's native /api/chat.
 *
 * Ollama ignores an Authorization header, but the caller's headers are
 * forwarded anyway: an Ollama box exposed through an authenticating reverse
 * proxy is common, and dropping the credential would break it for no gain.
 *
 * `stream` is pinned false. This app answers by polling, so a streamed
 * response would arrive as newline-delimited JSON that nothing here reads.
 */
class OllamaUpstream implements UpstreamDriver
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function send(Gateway $gateway, array $payload, array $headers): Response
    {
        return Http::withHeaders($headers)
            ->timeout($gateway->timeout)
            ->connectTimeout($gateway->connect_timeout)
            ->post($gateway->endpoint(), $this->body($payload))
            ->throw();
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    public function normalise(array $body): array
    {
        return [
            'id' => JsonValue::stringAt($body, 'created_at'),
            'object' => 'chat.completion',
            'model' => JsonValue::stringAt($body, 'model'),
            'choices' => [[
                'index' => 0,
                'message' => array_filter([
                    'role' => JsonValue::stringAt($body, 'message.role') ?: 'assistant',
                    'content' => JsonValue::stringAt($body, 'message.content'),
                    // Thinking models put the answer here when content is empty,
                    // which is the fallback ChatPayload::text() already knows.
                    'reasoning_content' => JsonValue::stringAt($body, 'message.thinking'),
                ], fn (string $value) => $value !== ''),
                'finish_reason' => $this->finishReason(JsonValue::stringAt($body, 'done_reason')),
            ]],
            'usage' => self::usage(
                JsonValue::intAt($body, 'prompt_eval_count'),
                JsonValue::intAt($body, 'eval_count'),
            ),
        ];
    }

    /**
     * A count the upstream did not report stays null rather than being summed
     * as a zero, so "it did not say" keeps reading differently from "none".
     *
     * @return array<string, int|null>
     */
    private static function usage(?int $promptTokens, ?int $completionTokens): array
    {
        $reported = array_filter([$promptTokens, $completionTokens], fn (?int $count) => $count !== null);

        return [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $reported === [] ? null : array_sum($reported),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function body(array $payload): array
    {
        return array_filter([
            'model' => JsonValue::stringAt($payload, 'model'),
            'messages' => $this->messages($payload),
            'stream' => false,
            'options' => $this->options($payload),
        ], fn (mixed $value) => $value !== '' && $value !== []);
    }

    /**
     * Ollama takes a system role inline, so unlike Anthropic nothing is
     * hoisted — only multimodal content parts are flattened, since Ollama
     * carries images in a separate `images` key this driver does not fill.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function messages(array $payload): array
    {
        $system = ChatPayload::systemPrompt($payload);

        return collect(ChatPayload::conversation($payload))
            ->map(fn (array $message) => [
                'role' => JsonValue::stringAt($message, 'role') ?: 'user',
                'content' => ChatPayload::contentText(JsonValue::at($message, 'content')),
            ])
            ->when($system !== '', fn (Collection $messages) => $messages->prepend([
                'role' => 'system',
                'content' => $system,
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function options(array $payload): array
    {
        return array_filter([
            'temperature' => JsonValue::at($payload, 'temperature'),
            'top_p' => JsonValue::at($payload, 'top_p'),
            'num_predict' => JsonValue::intAt($payload, 'max_tokens')
                ?? JsonValue::intAt($payload, 'max_completion_tokens'),
        ], fn (mixed $value) => $value !== null);
    }

    private function finishReason(string $doneReason): ?string
    {
        return match ($doneReason) {
            'stop' => 'stop',
            'length' => 'length',
            default => null,
        };
    }
}
