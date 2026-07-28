<a {{ $attributes->merge([
    'class' =>
        'font-medium text-zinc-700 underline decoration-zinc-400/60 underline-offset-4 transition hover:decoration-zinc-700 focus:outline-none focus-visible:rounded focus-visible:ring-2 focus-visible:ring-zinc-950/20',
]) }}>
    {{ $slot }}
</a>
