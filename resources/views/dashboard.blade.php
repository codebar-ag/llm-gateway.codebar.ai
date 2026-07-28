<x-layouts.app>
    <x-ui.page-header :title="__('dashboard.title')" :subtitle="__('dashboard.intro')" />

    <x-ui.card class="mt-8">
        @can('viewNova')
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-zinc-600">{{ __('dashboard.intro') }}</p>
                <x-ui.button variant="primary" :href="url(\Laravel\Nova\Nova::path())" class="shrink-0">
                    {{ __('dashboard.link_nova') }}
                </x-ui.button>
            </div>
        @else
            <p class="text-sm text-zinc-600">{{ __('dashboard.intro') }}</p>
        @endcan
    </x-ui.card>
</x-layouts.app>
