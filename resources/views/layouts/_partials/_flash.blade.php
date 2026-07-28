@php
    $flashMessage = optional(flash()->getMessage())->message;
@endphp
@if (session('microsoft_entra_sso_error') || $flashMessage)
    <div
        class="pointer-events-none fixed inset-x-0 top-0 z-50 flex w-full flex-col items-end gap-3 p-4 lg:p-6">
        @if (session('microsoft_entra_sso_error'))
            <div x-data="{ visible: true }" x-show="visible" x-collapse
                x-init="setTimeout(() => visible = false, 8000)"
                class="pointer-events-auto w-full lg:w-1/2">
                <div x-show="visible" x-transition>
                    <x-ui.callout icon="x-circle" variant="danger">
                        <x-slot name="heading">{{ session('microsoft_entra_sso_error') }}</x-slot>
                        <x-slot name="controls">
                            <x-ui.button variant="ghost" size="sm" iconName="x-mark" type="button"
                                x-on:click="visible = false" class="-mr-1 size-8 px-0" aria-label="Dismiss" />
                        </x-slot>
                    </x-ui.callout>
                </div>
            </div>
        @endif
        @if ($flashMessage)
            @php
                $variant = match (flash()->class) {
                    'success' => 'success',
                    'warning' => 'warning',
                    'error', 'danger' => 'danger',
                    default => 'secondary',
                };

                $icon = match (flash()->class) {
                    'success' => 'check-circle',
                    'warning' => 'exclamation-triangle',
                    'error', 'danger' => 'x-circle',
                    default => 'information-circle',
                };
            @endphp
            <div x-data="{ visible: true }" x-show="visible" x-collapse
                x-init="setTimeout(() => visible = false, 5000)"
                class="pointer-events-auto w-full lg:w-1/2">
                <div x-show="visible" x-transition>
                    <x-ui.callout :icon="$icon" :variant="$variant">
                        <x-slot name="heading">{{ $flashMessage }}</x-slot>
                        <x-slot name="controls">
                            <x-ui.button variant="ghost" size="sm" iconName="x-mark" type="button"
                                x-on:click="visible = false" class="-mr-1 size-8 px-0" aria-label="Dismiss" />
                        </x-slot>
                    </x-ui.callout>
                </div>
            </div>
        @endif
    </div>
@endif
