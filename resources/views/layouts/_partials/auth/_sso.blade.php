<div class="space-y-4">
    @foreach ($providers as $provider)
        @php
            $service = $provider instanceof \BackedEnum ? $provider->value : $provider;
        @endphp

        @if ($service === \App\Enums\ProviderEnum::MICROSOFT_OFFICE_365->value)
            <x-ui.button class="w-full" :href="route('microsoft-entra-sso.redirect', ['guard' => 'web'])">
                <x-slot name="icon">
                    <svg width="15" height="15" viewBox="0 0 25 24" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.5 0H0.5V11H11.5V0Z" fill="#F25022" />
                        <path d="M24.5 0H13.5V11H24.5V0Z" fill="#7FBA00" />
                        <path d="M11.5 13H0.5V24H11.5V13Z" fill="#00A4EF" />
                        <path d="M24.5 13H13.5V24H24.5V13Z" fill="#FFB900" />
                    </svg>
                </x-slot>
                {{ __('auth.sign-in-with-microsoft') }}
            </x-ui.button>
        @endif
    @endforeach
</div>

<x-ui.separator text="or" />
