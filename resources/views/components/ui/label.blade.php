@props([
    'for' => null,
])

<label @if ($for) for="{{ $for }}" @endif
    {{ $attributes->merge(['class' => 'block text-sm font-medium leading-none text-zinc-800']) }}>
    {{ $slot }}
</label>
