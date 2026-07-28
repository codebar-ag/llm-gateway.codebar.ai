@props([
    'icon' => null,
    'title' => null,
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-200 bg-zinc-50/50 px-6 py-12 text-center') }}>
    @if ($icon)
        <div class="mb-3 text-zinc-400">
            <x-ui.icon :name="$icon" class="size-8" />
        </div>
    @endif

    @if ($title)
        <p class="text-sm font-semibold text-zinc-900">{{ $title }}</p>
    @endif

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-zinc-500">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
