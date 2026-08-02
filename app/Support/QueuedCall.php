<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\BacklogStatus;
use App\Models\BacklogEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two answers every queued endpoint gives: the receipt for a request that
 * was accepted, and whatever the entry has become when the caller comes back
 * for it.
 *
 * Written once here rather than per endpoint so that embeddings, moderations
 * and anything queued later cannot drift on what a `202` looks like or on when
 * a wait turns into an answer. The chat endpoints keep their own hand-built
 * bodies: those shapes are OpenAI's, not this app's, and they predate this.
 */
final class QueuedCall
{
    /** The receipt: an id to poll, where to poll it, and what the wait looks like. */
    public static function accepted(Request $request, BacklogEntry $entry, ?QueuePlace $place): JsonResponse
    {
        return response()->json([
            'id' => $entry->id,
            'object' => $entry->kind->queuedObject(),
            'status' => $entry->status->value,
            'model' => $entry->model,
            'created' => $entry->created_at->timestamp,
            'poll_url' => PollUrl::for($request, $entry),
            'queue' => $place?->toArray(),
        ], 202);
    }

    /**
     * What the caller gets when they poll.
     *
     * A finished entry answers with the upstream's own body, exactly as it
     * arrived, so a client can hand it to an OpenAI SDK unchanged. Anything
     * still in flight answers `202` with the same receipt it was given — the
     * status code is what a polling loop reads, so "not yet" must never come
     * back as `200` with an empty body.
     */
    public static function collect(Request $request, BacklogEntry $entry, ?QueuePlace $place): JsonResponse
    {
        return match ($entry->status) {
            BacklogStatus::Completed => response()->json($entry->output ?? []),
            BacklogStatus::Failed => self::failed($entry),
            BacklogStatus::Cancelled => response()->json([
                'error' => [
                    'message' => 'This request was cancelled before a worker ran it.',
                    'type' => 'cancelled',
                ],
            ], 409),
            BacklogStatus::Queued, BacklogStatus::Running => self::waiting($request, $entry, $place),
        };
    }

    /**
     * The upstream's failure, given back with its own status where there was
     * one. A retry budget that ran out against an unreachable upstream has no
     * status of its own to report, and `502` says that better than a `200`
     * carrying an error nobody checked for.
     */
    private static function failed(BacklogEntry $entry): JsonResponse
    {
        $status = $entry->error['status'] ?? null;

        return response()->json([
            'error' => [
                'message' => $entry->error['message'] ?? 'The upstream did not answer.',
                'type' => 'upstream_error',
                'body' => $entry->error['body'] ?? null,
                'attempts' => $entry->attempts,
            ],
        ], is_int($status) && $status >= 400 && $status <= 599 ? $status : 502);
    }

    /** Still in line, or running. Carries the same `Retry-After` a polled response does. */
    private static function waiting(Request $request, BacklogEntry $entry, ?QueuePlace $place): JsonResponse
    {
        return self::accepted($request, $entry, $place)
            ->withHeaders(['Retry-After' => (string) max(1, $place->estimatedSeconds ?? 1)]);
    }
}
