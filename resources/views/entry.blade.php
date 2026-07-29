@php
    use App\Support\JsonHighlighter;

    $answer = data_get($entry->output, '0.content.0.text');
    $panel = 'rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
    $code = 'mt-2 overflow-auto whitespace-pre-wrap break-words rounded-xl border border-slate-200 bg-white p-4 font-mono text-xs dark:border-slate-800 dark:bg-slate-900';
    $heading = 'text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
@endphp

<x-layout :title="$entry->id">

    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('backlog') }}" class="text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                &larr; Back to backlog
            </a>
            <h1 class="mt-1 font-mono text-xl font-semibold tracking-tight">{{ $entry->id }}</h1>
        </div>
        <x-badge :tone="$entry->status->tone()" class="px-3 py-1 text-sm">{{ $entry->status->label() }}</x-badge>
    </header>

    @php
        $facts = [
            'Model' => $entry->model ?? '—',
            'Gateway' => $entry->gateway?->name ?? '—',
            'Upstream' => $entry->gateway?->endpoint() ?? '—',
            'Attempts' => $entry->attempts.' / '.$entry->max_attempts,
            'Total tokens' => $entry->usage['total_tokens'] ?? '—',
            'Waited' => $entry->waitedSeconds() !== null ? $entry->waitedSeconds().'s' : '—',
            'Duration' => $entry->durationSeconds() !== null ? $entry->durationSeconds().'s' : '—',
            'Forwarded headers' => count($entry->headerNames()) ?: '— (cleared)',
        ];
        $times = [
            'Created' => $entry->created_at,
            'Started' => $entry->started_at,
            'Finished' => $entry->finished_at,
            'Updated' => $entry->updated_at,
        ];
    @endphp

    <section class="mt-6 grid gap-3 sm:grid-cols-2">
        @foreach ($facts as $label => $value)
            <div class="{{ $panel }} p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</div>
                <div class="mt-1 truncate font-medium" title="{{ $value }}">{{ $value }}</div>
            </div>
        @endforeach

        @foreach ($times as $label => $moment)
            <div class="{{ $panel }} p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</div>
                <div class="mt-1 font-medium">{{ $moment?->toDateTimeString() ?? '—' }}</div>
                <div class="text-xs text-slate-400">{{ $moment?->diffForHumans() ?? 'not reached yet' }}</div>
            </div>
        @endforeach
    </section>

    @if ($entry->error)
        <section class="mt-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-red-600 dark:text-red-400">Error</h2>
            <pre class="mt-2 overflow-auto whitespace-pre-wrap break-words rounded-xl border border-red-200 bg-red-50 p-4 font-mono text-xs text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $entry->errorDetails() }}</pre>
        </section>
    @endif

    @if (filled($answer))
        <section class="mt-6">
            <h2 class="{{ $heading }}">Answer</h2>
            <pre class="mt-2 overflow-auto whitespace-pre-wrap break-words rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">{{ $answer }}</pre>
        </section>
    @endif

    <section class="mt-6">
        <h2 class="{{ $heading }}">Stored request</h2>
        @if ($entry->gateway && ! $entry->gateway->protocol->isPassthrough())
            <p class="mt-1 text-xs text-slate-400">
                Sent as {{ $entry->gateway->protocol->label() }}: the driver rewrites this body on the way out.
            </p>
        @endif
        <pre class="{{ $code }} max-h-[32rem]">{{ JsonHighlighter::render($entry->request) }}</pre>
    </section>

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        <div>
            <h2 class="{{ $heading }}">Usage</h2>
            <pre class="{{ $code }}">{{ JsonHighlighter::render($entry->usage) }}</pre>
        </div>
        <div>
            <h2 class="{{ $heading }}">Forwarded header names</h2>
            <pre class="{{ $code }}">{{ JsonHighlighter::render($entry->headerNames()) }}</pre>
            <p class="mt-1 text-xs text-slate-400">
                Values are never shown — they carry the caller's credentials.
            </p>
        </div>
    </section>

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        <div>
            <h2 class="{{ $heading }}">Raw output</h2>
            <pre class="{{ $code }} max-h-96">{{ JsonHighlighter::render($entry->output) }}</pre>
        </div>
        <div>
            <h2 class="{{ $heading }}">Raw error</h2>
            <pre class="{{ $code }} max-h-96">{{ JsonHighlighter::render($entry->error) }}</pre>
        </div>
    </section>

    <section class="mt-6">
        <h2 class="{{ $heading }}">API key hash</h2>
        <pre class="{{ $code }}">{{ $entry->api_key_hash ?? '—' }}</pre>
    </section>

</x-layout>
