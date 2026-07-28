@props([
    'hero' => true,
    'logoHref' => '/',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    @include('layouts._partials._head')

    <link rel="preconnect" href="https://res.cloudinary.com" crossorigin>
    <link rel="preload" as="image" href="{{ config('auth.hero.lq_url') }}">
</head>

<body class="font-sans antialiased">

    <main class="min-h-screen">
        @include('layouts._partials._flash')

        <div class="flex min-h-screen">
            @if ($hero)
                @include('auth.partials._hero-panel')
            @endif

            <div class="flex flex-1 items-center justify-center px-4">
                <div class="w-80 max-w-80 space-y-6">
                    <div class="flex justify-center opacity-50">
                        <a href="{{ $logoHref }}" class="group flex items-center gap-3">
                            <img src="{{ Vite::asset('resources/images/auth/logo.svg') }}"
                                alt="{{ config('app.name') }}" class="h-6 text-zinc-800" />
                        </a>
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </main>

</body>

</html>
