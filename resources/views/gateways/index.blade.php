<x-layout title="Gateways · LLM Gateway">

    <header class="flex flex-wrap items-baseline justify-between gap-2">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Gateways</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Where requests are sent. A caller picks one by putting its id in front of <code>/v1</code>;
                a caller that does not gets the default.
            </p>
        </div>
        <nav class="flex items-center gap-3 text-sm">
            <a href="{{ route('backlog') }}" class="text-slate-500 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Backlog</a>
            <a href="{{ route('gateways.create') }}"
               class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300">
                New gateway
            </a>
        </nav>
    </header>

    @if (session('status'))
        <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
            {{ session('status') }}
        </p>
    @endif

    @if (session('error'))
        <p class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
            {{ session('error') }}
        </p>
    @endif

    <section class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Default</th>
                    <th class="px-4 py-3 font-medium">Active</th>
                    <th class="px-4 py-3 font-medium">Protocol</th>
                    <th class="px-4 py-3 font-medium">Default model</th>
                    <th class="px-4 py-3 font-medium">Endpoint</th>
                    <th class="px-4 py-3 font-medium">Caller base URL</th>
                    <th class="px-4 py-3 text-right font-medium">Entries</th>
                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($gateways as $gateway)
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3 font-medium">{{ $gateway->name }}</td>
                        <td class="px-4 py-3 {{ $gateway->is_default ? 'font-medium text-emerald-700 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500' }}">{{ $gateway->is_default ? 'yes' : 'no' }}</td>
                        <td class="px-4 py-3 {{ $gateway->is_active ? '' : 'font-medium text-red-700 dark:text-red-400' }}">{{ $gateway->is_active ? 'yes' : 'no' }}</td>
                        <td class="px-4 py-3">
                            <x-badge :tone="$gateway->protocol->tone()" class="inline-block px-2 py-0.5 text-xs">
                                {{ $gateway->protocol->label() }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs {{ $gateway->default_model ? 'text-slate-500 dark:text-slate-400' : 'text-slate-300 dark:text-slate-600' }}">{{ $gateway->default_model ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="block max-w-xs truncate font-mono text-xs text-slate-500 dark:text-slate-400"
                                  title="{{ $gateway->endpoint() }}">{{ $gateway->endpoint() }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="flex items-center gap-2">
                                <code class="font-mono text-xs text-slate-500 dark:text-slate-400"
                                      title="{{ $gateway->callerBaseUrl() }}">/{{ substr($gateway->id, 0, 8) }}…/v1</code>
                                <x-copy-button :value="$gateway->callerBaseUrl()" />
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <a href="{{ route('backlog', ['gateway' => $gateway->id]) }}" class="underline hover:text-slate-900 dark:hover:text-slate-100">
                                {{ $gateway->entries_count }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                @unless ($gateway->is_default)
                                    <form method="POST" action="{{ route('gateways.default', $gateway) }}">
                                        @csrf
                                        <button type="submit"
                                                class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium hover:border-slate-900 dark:border-slate-700 dark:hover:border-slate-100">
                                            Make default
                                        </button>
                                    </form>
                                @endunless

                                <a href="{{ route('gateways.edit', $gateway) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium hover:border-slate-900 hover:bg-slate-900 hover:text-white dark:border-slate-700 dark:hover:border-slate-100 dark:hover:bg-slate-100 dark:hover:text-slate-900">
                                    Edit
                                </a>

                                @unless ($gateway->is_default)
                                    <form method="POST" action="{{ route('gateways.destroy', $gateway) }}"
                                          onsubmit="return confirm('Retire {{ $gateway->name }}? Queued requests already accepted for it still run.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded-lg border border-red-300 px-3 py-1 text-xs font-medium text-red-700 hover:border-red-600 hover:bg-red-600 hover:text-white dark:border-red-900 dark:text-red-400">
                                            Retire
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-slate-400">
                            No gateways yet. Nothing can be queued until one exists.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <footer class="mt-4 text-xs text-slate-500 dark:text-slate-400">
        Retiring a gateway is a soft delete, so the entries it answered keep their attribution.
        Deactivating one stops it taking new requests without touching what is already queued for it.
    </footer>

</x-layout>
