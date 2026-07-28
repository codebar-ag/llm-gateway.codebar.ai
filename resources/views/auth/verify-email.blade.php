<x-layouts.guest>
    <x-ui.heading class="text-center" size="xl">{{ __('auth.verify-email') }}</x-ui.heading>

    <form class="flex flex-col gap-6" action="{{ route('auth.verification.send') }}" method="POST">
        @csrf
        @honeypot

        <x-ui.button variant="primary" class="w-full" type="submit">{{ __('auth.resend-verification-email') }}</x-ui.button>
    </form>
</x-layouts.guest>
