<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    @include('layouts._partials._head')
</head>

<body x-data="{ open: false }" class="min-h-screen bg-zinc-50 font-sans antialiased" :class="{ 'overflow-hidden': open }">

    @include('layouts._partials._flash')

    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2">
                <img src="{{ Vite::asset('resources/images/auth/logo.svg') }}" alt="{{ config('app.name') }}"
                    class="h-6 text-zinc-800" />
            </a>

            <x-ui.nav class="max-sm:hidden">
                <x-ui.nav-link :href="url('/')" iconName="home">{{ __('dashboard.title') }}</x-ui.nav-link>
                @can('viewNova')
                    <x-ui.nav-link :href="url(\Laravel\Nova\Nova::path())">{{ __('dashboard.link_nova') }}</x-ui.nav-link>
                @endcan
            </x-ui.nav>

            <div class="flex items-center gap-2">
                <form method="post" action="{{ route('auth.logout') }}" class="max-sm:hidden">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm" iconName="arrow-right-on-rectangle">
                        {{ __('dashboard.sign_out') }}
                    </x-ui.button>
                </form>

                <x-ui.button type="button" variant="ghost" size="sm" iconName="bars-3" class="sm:hidden"
                    x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="Toggle navigation" />
            </div>
        </div>

        <div x-show="open" x-collapse x-cloak class="border-t border-zinc-200 sm:hidden">
            <nav class="mx-auto flex max-w-5xl flex-col gap-1 px-4 py-3" aria-label="Primary">
                <x-ui.nav-link :href="url('/')" iconName="home">{{ __('dashboard.title') }}</x-ui.nav-link>
                @can('viewNova')
                    <x-ui.nav-link :href="url(\Laravel\Nova\Nova::path())">{{ __('dashboard.link_nova') }}</x-ui.nav-link>
                @endcan
                <form method="post" action="{{ route('auth.logout') }}" class="pt-1">
                    @csrf
                    <x-ui.button type="submit" variant="ghost" size="sm" iconName="arrow-right-on-rectangle"
                        class="w-full justify-start">
                        {{ __('dashboard.sign_out') }}
                    </x-ui.button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10">
        {{ $slot }}
    </main>

</body>

</html>
