@props(['value'])

{{--
    Copies one long string — a caller base URL — that the tables shorten so the
    layout stays readable. The listener is delegated from the document once, so
    any number of these on a page cost a single handler.
--}}

<button type="button" data-copy="{{ $value }}"
        {{ $attributes->class('shrink-0 rounded-md border border-slate-300 px-2 py-0.5 text-xs font-medium text-slate-500 hover:border-slate-900 hover:text-slate-900 dark:border-slate-700 dark:text-slate-400 dark:hover:border-slate-100 dark:hover:text-slate-100') }}>
    Copy
</button>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-copy]');

                if (! button || ! navigator.clipboard) {
                    return;
                }

                navigator.clipboard.writeText(button.dataset.copy).then(function () {
                    button.textContent = 'Copied';
                    setTimeout(function () {
                        button.textContent = 'Copy';
                    }, 1500);
                });
            });
        </script>
    @endpush
@endonce
