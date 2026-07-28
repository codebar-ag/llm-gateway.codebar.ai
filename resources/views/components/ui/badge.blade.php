@props([
    'variant' => 'secondary',
])

@php
    $variantClass = match ($variant) {
        'success' => 'border-green-200 bg-green-50 text-green-700',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        'danger' => 'border-red-200 bg-red-50 text-red-700',
        'accent' => 'border-transparent bg-[var(--color-accent)] text-[var(--color-accent-foreground)]',
        default => 'border-zinc-200 bg-zinc-50 text-zinc-700',
    };
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
        $variantClass,
    ]) }}>
    {{ $slot }}
</span>
