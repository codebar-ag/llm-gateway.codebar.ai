@props([
    'label' => null,
    'name',
    'placeholder' => null,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $errors->has($name);
@endphp

<div @class(['space-y-2', $attributes->get('class')])>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium leading-none text-zinc-800">{{ $label }}</label>
    @endif

    <select id="{{ $id }}" name="{{ $name }}"
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->except(['class', 'id'])->merge([
            'class' =>
                'block h-10 w-full rounded-lg border bg-white px-3 text-sm text-zinc-900 shadow-sm transition focus:outline-none focus:ring-2 '
                .($hasError
                    ? 'border-red-400 focus:border-red-400 focus:ring-red-500/20'
                    : 'border-zinc-200 focus:border-zinc-300 focus:ring-zinc-950/10'),
        ]) }}>
        @if ($placeholder !== null)
            <option value="" disabled selected>{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>

    @error($name)
        <p id="{{ $id }}-error" class="text-sm font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
