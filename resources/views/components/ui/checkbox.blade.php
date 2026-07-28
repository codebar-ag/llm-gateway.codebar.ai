@props([
    'label' => null,
    'name',
])

@php
    $id = $attributes->get('id', $name);
@endphp

<div class="flex items-center gap-2">
    <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="1" @checked(old($name))
        {{ $attributes->except('id')->merge([
            'class' => 'size-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-2 focus:ring-zinc-950/10',
        ]) }} />

    @if ($label)
        <label for="{{ $id }}" class="cursor-pointer text-sm font-medium text-zinc-800">{{ $label }}</label>
    @endif
</div>
