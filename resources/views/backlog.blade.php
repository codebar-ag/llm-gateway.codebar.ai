<x-layout refresh="15">

    <header class="flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="text-2xl font-semibold tracking-tight">LLM Gateway</h1>
        <p class="flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
            <x-badge :tone="$tunnel->tone()" class="px-2 py-0.5 text-xs">{{ $tunnel->label() }}</x-badge>
            <span>
                {{ $workers }} {{ Str::plural('worker', $workers) }} &rarr;
                {{ $defaultGateway?->endpoint() ?? 'no default gateway' }}
            </span>
            <a href="{{ route('gateways') }}" class="underline hover:text-slate-900 dark:hover:text-slate-100">Gateways</a>
        </p>
    </header>

    <section class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ($statuses as $case)
            @php($active = $filters->status === $case)
            {{-- `cursor`, not `page`: the listing is cursor paginated, and carrying a
                 cursor taken from one filter's result set into another silently drops
                 every row that sat before it. --}}
            <a href="{{ request()->fullUrlWithQuery(['status' => $active ? null : $case->value, 'cursor' => null]) }}"
               class="rounded-xl border p-4 transition
                      {{ $active
                          ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900'
                          : 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-600' }}">
                <div class="text-2xl font-semibold tabular-nums">{{ $counts[$case->value] ?? 0 }}</div>
                <div class="mt-1 text-xs uppercase tracking-wide opacity-70">{{ $case->label() }}</div>
            </a>
        @endforeach
    </section>

    @if ($oldestQueued)
        <p class="mt-3 text-sm text-amber-600 dark:text-amber-400">
            Oldest queued entry is waiting since {{ $oldestQueued->diffForHumans() }}.
        </p>
    @endif

    <form method="GET" action="{{ route('backlog') }}"
          class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div class="min-w-64 flex-1">
            <label for="q" class="block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Entry ID</label>
            <input type="search" name="q" id="q" value="{{ $filters->search }}" placeholder="resp_…"
                   class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm placeholder:text-slate-400 focus:border-slate-900 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-300">
        </div>

        <div>
            <label for="model" class="block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Model</label>
            <select name="model" id="model"
                    class="mt-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-300">
                <option value="">All models</option>
                @foreach ($models as $option)
                    <option value="{{ $option }}" @selected($filters->model === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="gateway" class="block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Gateway</label>
            <select name="gateway" id="gateway"
                    class="mt-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-300">
                <option value="">All gateways</option>
                @foreach ($gateways as $option)
                    <option value="{{ $option->id }}" @selected($filters->gateway === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($filters->status)
            <input type="hidden" name="status" value="{{ $filters->status->value }}">
        @endif

        <button type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300">
            Search
        </button>

        @unless ($filters->areEmpty())
            <a href="{{ route('backlog') }}" class="px-2 py-2 text-sm text-slate-500 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                Reset
            </a>
        @endunless
    </form>

    <section class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Entry</th>
                    <th class="px-4 py-3 font-medium">Model</th>
                    <th class="px-4 py-3 font-medium">Gateway</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 text-right font-medium">Attempts</th>
                    <th class="px-4 py-3 text-right font-medium">Waited</th>
                    <th class="px-4 py-3 text-right font-medium">Duration</th>
                    <th class="px-4 py-3 text-right font-medium">Created</th>
                    <th class="px-4 py-3 text-right font-medium">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($entries as $entry)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $entry->id }}</td>
                        <td class="px-4 py-3">{{ $entry->model ?? "—" }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $entry->gateway?->name ?? "—" }}</td>
                        <td class="px-4 py-3">
                            <x-badge :tone="$entry->status->tone()" class="inline-block px-2 py-0.5 text-xs">{{ $entry->status->label() }}</x-badge>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $entry->attempts }}/{{ $entry->max_attempts }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-500 dark:text-slate-400">
                            {{ $entry->waitedSeconds() !== null ? $entry->waitedSeconds().'s' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-500 dark:text-slate-400">
                            {{ $entry->durationSeconds() !== null ? $entry->durationSeconds().'s' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400" title="{{ $entry->created_at }}">
                            {{ $entry->created_at->diffForHumans(short: true) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('backlog.entry', $entry->id) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium hover:border-slate-900 hover:bg-slate-900 hover:text-white dark:border-slate-700 dark:hover:border-slate-100 dark:hover:bg-slate-100 dark:hover:text-slate-900">
                                Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                            No entries match this filter.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <footer class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
        <span>
            Showing {{ $entries->count() }} of {{ $total }} {{ Str::plural('entry', $total) }}
        </span>

        @if ($entries->hasPages())
            <nav class="flex gap-2">
                @if ($entries->previousPageUrl())
                    <a href="{{ $entries->previousPageUrl() }}" class="rounded-lg border border-slate-300 px-3 py-1 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-100">Previous</a>
                @else
                    <span class="rounded-lg border border-slate-200 px-3 py-1 text-slate-300 dark:border-slate-800 dark:text-slate-700">Previous</span>
                @endif

                @if ($entries->nextPageUrl())
                    <a href="{{ $entries->nextPageUrl() }}" class="rounded-lg border border-slate-300 px-3 py-1 hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-100">Next</a>
                @else
                    <span class="rounded-lg border border-slate-200 px-3 py-1 text-slate-300 dark:border-slate-800 dark:text-slate-700">Next</span>
                @endif
            </nav>
        @endif

        <span>Auto-refreshes every 15&thinsp;s</span>
    </footer>

</x-layout>
