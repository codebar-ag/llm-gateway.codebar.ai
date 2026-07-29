<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\ApiKeyLabel;
use Illuminate\Http\RedirectResponse;

class DestroyCallerLabelController
{
    public function __invoke(string $hash): RedirectResponse
    {
        // Only the name goes. The entries keep their attribution, which is the
        // hash itself — this table never held anything they depend on.
        ApiKeyLabel::query()->where('api_key_hash', $hash)->delete();

        return redirect()
            ->route('callers')
            ->with('status', 'Name removed. The caller is shown by its hash again.');
    }
}
