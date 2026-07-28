<x-layouts.guest>
    <x-ui.heading class="text-center" size="xl">{{ __('auth.login') }}</x-ui.heading>

    @if ($providers->count())
        @include('layouts._partials.auth._sso')
    @endif

    <form class="flex flex-col gap-6" action="{{ route('auth.login.store') }}" method="POST">
        @csrf
        @honeypot

        <x-ui.input type="email" name="email" :label="__('auth.email')" :placeholder="__('auth.email')" required />

        <x-ui.input type="password" name="password" :label="__('auth.password')" :placeholder="__('auth.password')"
            required />

        <x-ui.checkbox name="remember" :label="__('auth.remember-me')" />

        <x-ui.button variant="primary" class="w-full" type="submit">{{ __('auth.sign-in') }}</x-ui.button>
    </form>

    <x-ui.subheading class="text-center">
        <x-ui.link href="{{ route('auth.request-password') }}">{{ __('auth.forgot-password') }}</x-ui.link>
    </x-ui.subheading>
</x-layouts.guest>
