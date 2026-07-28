@props([
    'href' => '#',
    'active' => null,
    'iconName' => null,
])

@php
    $isActive = $active ?? ($href !== '#' && request()->fullUrlIs(url($href).'*'));

    $stateClass = $isActive
        ? 'bg-zinc-100 text-zinc-900'
        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900';
@endphp

<a href="{{ $href }}" @if ($isActive) aria-current="page" @endif
    {{ $attributes->merge([
        'class' =>
            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950/20 '
            .$stateClass,
    ]) }}>
    @if ($iconName)
        <x-ui.icon :name="$iconName" class="size-4" />
    @endif

    {{ $slot }}
</a>
