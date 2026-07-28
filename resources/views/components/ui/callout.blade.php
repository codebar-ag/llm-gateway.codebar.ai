@props([
    'variant' => 'secondary',
    'icon' => null,
])

@php
    $styles = match ($variant) {
        'success' => [
            'wrap' => 'border-green-200 bg-green-50',
            'icon' => 'text-green-600',
            'heading' => 'text-green-900',
        ],
        'warning' => [
            'wrap' => 'border-amber-200 bg-amber-50',
            'icon' => 'text-amber-600',
            'heading' => 'text-amber-900',
        ],
        'danger' => [
            'wrap' => 'border-red-200 bg-red-50',
            'icon' => 'text-red-600',
            'heading' => 'text-red-900',
        ],
        default => [
            'wrap' => 'border-zinc-200 bg-zinc-50',
            'icon' => 'text-zinc-500',
            'heading' => 'text-zinc-900',
        ],
    };
@endphp

<div
    {{ $attributes->class([
        'flex gap-3 rounded-xl border p-4 shadow-sm',
        $styles['wrap'],
    ]) }}
    role="status">
    @if ($icon)
        <div @class(['shrink-0 pt-0.5', $styles['icon']])>
            <x-ui.icon :name="$icon" class="size-5" />
        </div>
    @endif

    <div class="min-w-0 flex-1 space-y-1">
        @isset($heading)
            <p @class(['text-sm font-semibold', $styles['heading']])>{{ $heading }}</p>
        @endisset

        @if ($slot->isNotEmpty())
            <div class="text-sm text-zinc-700">{{ $slot }}</div>
        @endif
    </div>

    @isset($controls)
        <div class="flex shrink-0 items-start">{{ $controls }}</div>
    @endisset
</div>
