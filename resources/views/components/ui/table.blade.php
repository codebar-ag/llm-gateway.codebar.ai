@props([
    'head' => null,
])

<div {{ $attributes->class('overflow-x-auto rounded-xl border border-zinc-200') }}>
    <table class="w-full border-collapse text-left text-sm">
        @isset($head)
            <thead class="border-b border-zinc-200 bg-zinc-50">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset

        <tbody class="divide-y divide-zinc-200">
            {{ $slot }}
        </tbody>
    </table>
</div>
