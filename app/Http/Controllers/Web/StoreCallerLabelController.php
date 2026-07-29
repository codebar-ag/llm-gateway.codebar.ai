<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\CallerLabelRequest;
use App\Models\ApiKeyLabel;
use Illuminate\Http\RedirectResponse;

class StoreCallerLabelController
{
    public function __invoke(CallerLabelRequest $request): RedirectResponse
    {
        // Upsert on the hash, not on an id: the page names a caller it observed
        // in the backlog, and whether that hash was named before is not
        // something the operator should have to know.
        ApiKeyLabel::query()->updateOrCreate(
            ['api_key_hash' => $request->string('api_key_hash')->value()],
            ['name' => $request->string('name')->value()],
        );

        return redirect()
            ->route('callers')
            ->with('status', 'Caller named.');
    }
}
