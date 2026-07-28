<x-layouts.guest>
    <x-ui.heading class="text-center" size="xl">{{ __('auth.forgot-your-password') }}</x-ui.heading>

    <form class="flex flex-col gap-6" action="{{ route('auth.request-password.store') }}" method="POST">
        @csrf
        @honeypot

        <x-ui.input type="email" name="email" :label="__('auth.email')" :placeholder="__('auth.email')" required />

        <x-ui.button variant="primary" class="w-full" type="submit">{{ __('auth.request-password-reset') }}</x-ui.button>
    </form>

    <x-ui.subheading class="text-center">
        <x-ui.link href="{{ route('auth.login') }}">{{ __('auth.back-to-login') }}</x-ui.link>
    </x-ui.subheading>
</x-layouts.guest>
