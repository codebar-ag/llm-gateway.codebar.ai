<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BacklogEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The OpenAI Responses API shape. It lives here rather than on the model so
 * that changing what the API returns is not a change to the domain object.
 */
class BacklogEntryResource extends JsonResource
{
    public function __construct(private readonly BacklogEntry $entry)
    {
        parent::__construct($entry);
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
        ];
    }
}
