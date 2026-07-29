<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tunnel;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * What every page gets without asking for it.
 *
 * The tunnel indicator and the flash banners appear in the shell rather than on
 * one page, so they are shared here instead of being repeated in four
 * controllers. Everything else a page needs is that page's own prop.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'name' => config('app.name'),

            /*
             | Name and address only. The layout greets the operator with it,
             | and anything more would put a serialised user model into the
             | HTML of every page for no one to read.
             */
            'auth' => [
                'user' => $request->user() === null ? null : [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ],

            'nav' => [
                'backlog' => route('backlog'),
                'callers' => route('callers'),
                'gateways' => route('gateways'),
                'logout' => route('logout'),
            ],

            /*
             | Lazily resolved: the tunnel probe is an HTTP call to cloudflared's
             | metrics port, and a partial reload asking only for `entries` has
             | no reason to pay for it. `Tunnel` caches for 10s regardless.
             */
            'tunnel' => fn (): array => $this->tunnel(),

            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }

    /** @return array{state: string, label: string, tone: string} */
    private function tunnel(): array
    {
        $state = app(Tunnel::class)->state();

        return [
            'state' => $state->value,
            'label' => $state->label(),
            'tone' => $state->tone(),
        ];
    }
}
