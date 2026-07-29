@php($editing = $gateway->exists)

<x-layout :title="($editing ? 'Edit gateway' : 'New gateway').' · LLM Gateway'">

    @php($input = 'mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-300')
    @php($label = 'block text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400')
    @php($hint = 'mt-1 text-xs text-slate-500 dark:text-slate-400')

    <header class="flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? 'Edit gateway' : 'New gateway' }}</h1>
        <a href="{{ route('gateways') }}" class="text-sm text-slate-500 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
            Back to gateways
        </a>
    </header>

    @if ($errors->any())
        <ul class="mt-4 space-y-1 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ $editing ? route('gateways.update', $gateway) : route('gateways.store') }}"
          class="mt-6 max-w-3xl space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div>
            <label for="name" class="{{ $label }}">Name</label>
            <input type="text" name="name" id="name" required value="{{ old('name', $gateway->name) }}" class="{{ $input }}">
            <p class="{{ $hint }}">Operator-facing only. Callers address a gateway by its id, never by its name.</p>
        </div>

        <div>
            <label for="protocol" class="{{ $label }}">Protocol</label>
            <select name="protocol" id="protocol" class="{{ $input }}">
                @foreach ($protocols as $protocol)
                    <option value="{{ $protocol->value }}" @selected(old('protocol', $gateway->protocol?->value) === $protocol->value)>
                        {{ $protocol->label() }}
                    </option>
                @endforeach
            </select>
            <p class="{{ $hint }}">
                OpenAI-compatible upstreams get the caller's body untouched. Anthropic and Ollama are
                translated on the way out and back, and tool calls are not translated.
            </p>
        </div>

        <div>
            <label for="base_url" class="{{ $label }}">Base URL</label>
            <input type="url" name="base_url" id="base_url" required value="{{ old('base_url', $gateway->base_url) }}"
                   placeholder="https://llm.codebar.net" class="{{ $input }} font-mono">
        </div>

        <div>
            <label for="path" class="{{ $label }}">Path override</label>
            <input type="text" name="path" id="path" value="{{ old('path', $gateway->path) }}"
                   placeholder="{{ $gateway->protocol?->defaultPath() ?? '/v1/chat/completions' }}" class="{{ $input }} font-mono">
            <p class="{{ $hint }}">Leave empty to use the protocol's own path. Set it for upstreams behind a prefix.</p>
        </div>

        <div>
            <label for="default_model" class="{{ $label }}">Default model</label>
            <input type="text" name="default_model" id="default_model" value="{{ old('default_model', $gateway->default_model) }}"
                   class="{{ $input }} font-mono">
            <p class="{{ $hint }}">Used only when the caller's request names no model. A request that names one is never overridden.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="timeout" class="{{ $label }}">Timeout (s)</label>
                <input type="number" name="timeout" id="timeout" required min="1" max="{{ $maxTimeout }}"
                       value="{{ old('timeout', $gateway->timeout) }}" class="{{ $input }}">
                <p class="{{ $hint }}">At most {{ $maxTimeout }}s — the ceiling the queue's retry window is built on.</p>
            </div>

            <div>
                <label for="connect_timeout" class="{{ $label }}">Connect timeout (s)</label>
                <input type="number" name="connect_timeout" id="connect_timeout" required min="1" max="120"
                       value="{{ old('connect_timeout', $gateway->connect_timeout) }}" class="{{ $input }}">
            </div>
        </div>

        <div>
            <label for="description" class="{{ $label }}">Description</label>
            <textarea name="description" id="description" rows="2" class="{{ $input }}">{{ old('description', $gateway->description) }}</textarea>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $gateway->is_active ?? true))
                   class="rounded border-slate-300 dark:border-slate-700">
            Accepts new requests
        </label>

        <div class="flex items-center gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
            <button type="submit"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300">
                {{ $editing ? 'Save changes' : 'Create gateway' }}
            </button>
            <a href="{{ route('gateways') }}" class="text-sm text-slate-500 underline hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Cancel</a>
        </div>
    </form>

    @if ($editing)
        <p class="mt-4 max-w-3xl text-xs text-slate-500 dark:text-slate-400">
            Callers reach this gateway at <code class="font-mono">{{ $gateway->callerBaseUrl() }}</code>.
        </p>
    @endif

</x-layout>
