@props([
    'text' => null,
])

<div {{ $attributes->class('relative') }}>
    <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <span class="w-full border-t border-zinc-200"></span>
    </div>
    @if ($text)
        <div class="relative flex justify-center text-xs font-medium uppercase tracking-wide">
            <span class="bg-white px-2 text-zinc-500">{{ $text }}</span>
        </div>
    @endif
</div>
