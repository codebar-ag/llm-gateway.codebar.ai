@props([
    'title' => null,
    'subtitle' => null,
])

<div {{ $attributes->class('space-y-4') }}>
    @isset($breadcrumbs)
        {{ $breadcrumbs }}
    @endisset

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            @if ($title)
                <x-ui.heading as="h1" size="xl">{{ $title }}</x-ui.heading>
            @endif

            @if ($subtitle)
                <x-ui.subheading>{{ $subtitle }}</x-ui.subheading>
            @endif

            @if ($slot->isNotEmpty())
                {{ $slot }}
            @endif
        </div>

        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
