<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Gateway;
use Illuminate\Contracts\View\View;

class ListGatewaysController
{
    public function __invoke(): View
    {
        return view('gateways.index', [
            'gateways' => Gateway::query()
                ->withCount('entries')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
