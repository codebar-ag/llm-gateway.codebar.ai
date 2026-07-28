@props([
    'label' => null,
    'name' => null,
    'for' => null,
    'help' => null,
    'error' => null,
])

@php
    $controlId = $for ?? $name;
    $message = $error ?? ($name ? $errors->first($name) : null);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    @if ($label)
        <x-ui.label :for="$controlId">{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @if ($help && ! $message)
        <p class="text-sm text-zinc-500">{{ $help }}</p>
    @endif

    @if ($message)
        <p @if ($controlId) id="{{ $controlId }}-error" @endif class="text-sm font-medium text-red-600">{{ $message }}</p>
    @endif
</div>
