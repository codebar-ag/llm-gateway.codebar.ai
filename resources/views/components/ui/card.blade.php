@props([
    'padding' => 'p-6',
])

<div {{ $attributes->class('rounded-xl border border-zinc-200 bg-white shadow-sm') }}>
    @isset($header)
        <div class="border-b border-zinc-200 px-6 py-4">{{ $header }}</div>
    @endisset

    <div class="{{ $padding }}">{{ $slot }}</div>

    @isset($footer)
        <div class="border-t border-zinc-200 px-6 py-4">{{ $footer }}</div>
    @endisset
</div>
