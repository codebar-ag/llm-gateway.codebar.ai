@props([
    'as' => 'h2',
    'size' => 'lg',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'text-lg font-semibold',
        'lg' => 'text-xl font-semibold',
        'xl' => 'text-2xl font-semibold tracking-tight',
        default => 'text-xl font-semibold',
    };
@endphp

<{{ $as }} {{ $attributes->class([$sizeClass, 'text-zinc-900']) }}>
    {{ $slot }}
</{{ $as }}>
