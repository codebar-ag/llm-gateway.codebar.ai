@props([
    'items' => [],
])

<nav {{ $attributes->merge(['class' => 'text-sm']) }} aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-2">
        @foreach ($items as $item)
            <li class="flex items-center gap-2 first:[&>[data-sep]]:hidden">
                <span data-sep class="text-zinc-300" aria-hidden="true">
                    <x-ui.icon name="chevron-right" class="size-4" />
                </span>

                @if ($loop->last || empty($item['href']))
                    <span @if ($loop->last) aria-current="page" @endif class="font-medium text-zinc-500">{{ $item['label'] }}</span>
                @else
                    <a href="{{ $item['href'] }}"
                        class="text-zinc-600 transition hover:text-zinc-900 focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-zinc-950/20">{{ $item['label'] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
