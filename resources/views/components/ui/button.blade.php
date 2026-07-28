@props([
    'variant' => 'default',
    'size' => 'md',
    'type' => 'button',
    'iconName' => null,
    'href' => null,
])

@php
    $base =
        'relative inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-lg font-medium whitespace-nowrap transition focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-950/20 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

    $variantClass = match ($variant) {
        'primary' => 'bg-[var(--color-accent)] text-[var(--color-accent-foreground)] shadow-sm hover:opacity-90 active:opacity-95',
        'ghost' => 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus-visible:ring-red-600',
        default => 'border border-zinc-200 bg-white text-zinc-800 shadow-sm hover:bg-zinc-50',
    };

    $sizeClass = match ($size) {
        'sm' => 'h-8 px-3 text-sm',
        'lg' => 'h-12 px-6 text-base',
        default => 'h-10 px-4 text-sm',
    };

    $class = $base.' '.$variantClass.' '.$sizeClass;
@endphp

@if (filled($href))
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>
        @isset($icon)
            <span class="inline-flex shrink-0 items-center" aria-hidden="true">{{ $icon }}</span>
        @elseif ($iconName)
            <x-ui.icon :name="$iconName" class="size-4" />
        @endif

        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>
        @isset($icon)
            <span class="inline-flex shrink-0 items-center" aria-hidden="true">{{ $icon }}</span>
        @elseif ($iconName)
            <x-ui.icon :name="$iconName" class="size-4" />
        @endif

        {{ $slot }}
    </button>
@endif
