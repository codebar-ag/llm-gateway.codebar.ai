<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\BacklogEntry;
use Illuminate\Contracts\View\View;

class ShowBacklogEntryController
{
    public function __invoke(string $id): View
    {
        // The page names the gateway four times; without this it is a second
        // query rather than a violation, since strict mode only rejects a lazy
        // load once more than one model was hydrated.
        return view('entry', ['entry' => BacklogEntry::query()->with('gateway')->findOrFail($id)]);
    }
}
