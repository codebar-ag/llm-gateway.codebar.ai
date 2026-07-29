<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Gateway;
use App\Support\GatewayPayload;
use Inertia\Inertia;
use Inertia\Response;

class ListGatewaysController
{
    public function __invoke(): Response
    {
        return Inertia::render('Gateways/Index', [
            'gateways' => Gateway::query()
                ->withCount('entries')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (Gateway $gateway): array => GatewayPayload::row($gateway))
                ->all(),
            'urls' => ['create' => route('gateways.create')],
        ]);
    }
}
