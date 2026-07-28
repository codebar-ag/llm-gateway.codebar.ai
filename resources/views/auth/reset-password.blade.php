<x-layouts.guest>
    <x-ui.heading class="text-center" size="xl">{{ __('auth.reset-your-password') }}</x-ui.heading>

    <form class="flex flex-col gap-6" action="{{ route('auth.reset-password.store') }}" method="POST">
        @csrf
        @honeypot

        <input type="hidden" name="token" value="{{ $token }}" />

        <x-ui.input type="email" name="email" :label="__('auth.email')" :value="$email"
            :placeholder="__('auth.email')" readonly required />

        <x-ui.input type="password" name="password" :label="__('auth.password')" :placeholder="__('auth.password')"
            required />

        <x-ui.input type="password" name="password_confirmation" :label="__('auth.confirm-password')"
            :placeholder="__('auth.confirm-password')" required />

        <x-ui.button variant="primary" class="w-full" type="submit">{{ __('auth.reset-password') }}</x-ui.button>
    </form>

    <x-ui.subheading class="text-center">
        <x-ui.link href="{{ route('auth.login') }}">{{ __('auth.back-to-login') }}</x-ui.link>
    </x-ui.subheading>
</x-layouts.guest>
