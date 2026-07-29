<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BacklogEntry;
use App\Support\QueuePlace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The OpenAI Responses API shape. It lives here rather than on the model so
 * that changing what the API returns is not a change to the domain object.
 *
 * `queue` is the one addition to that shape, kept in a key of its own so the
 * spec's own fields stay exactly as an SDK expects to find them.
 */
class BacklogEntryResource extends JsonResource
{
    private ?QueuePlace $place = null;

    public function __construct(private readonly BacklogEntry $entry)
    {
        parent::__construct($entry);
    }

    /**
     * The place is handed in rather than looked up here: `collection()`
     * builds one of these per row, and counting a queue position per row
     * would be a query per entry on the endpoint that returns the most of
     * them. Listing therefore reports no place at all.
     *
     * Set through a method rather than a second constructor argument because
     * `mapInto()` builds each resource in a collection with the item *and its
     * key*, which would silently arrive here as the place.
     */
    public function withPlace(?QueuePlace $place): self
    {
        $this->place = $place;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->entry->id,
            'object' => 'response',
            'created_at' => $this->entry->created_at->timestamp,
            'status' => $this->entry->status->value,
            'model' => $this->entry->model,
            'output' => $this->entry->output ?? [],
            'usage' => $this->entry->usage,
            'error' => $this->entry->error,
            'queue' => $this->place?->toArray(),
        ];
    }
}
