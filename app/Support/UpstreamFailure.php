<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

class UpstreamFailure
{
    /**
     * Describe why an entry failed, passing the upstream answer through
     * unchanged so nothing about the cause is lost.
     *
     * @return array<string, mixed>
     */
    public static function describe(Throwable $exception): array
    {
        if (! $exception instanceof RequestException) {
            return [
                'type' => 'exception',
                'message' => $exception::class.': '.$exception->getMessage(),
            ];
        }

        $status = $exception->response->status();

        return [
            'type' => 'upstream',
            'status' => $status,
            // Named for the role, not the product: a gateway may be LiteLLM,
            // Anthropic or Ollama, and the entry says which one it was.
            'message' => "Upstream responded with HTTP {$status}",
            'body' => self::body($exception->response),
        ];
    }

    private static function body(Response $response): mixed
    {
        $decoded = $response->json();

        return is_array($decoded) ? $decoded : $response->body();
    }
}
