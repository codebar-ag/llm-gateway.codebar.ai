@props([
    'statusCode' => 500,
    'title' => '',
    'message' => '',
])

<x-layouts.guest>
    <p class="text-center text-sm font-medium tabular-nums text-zinc-500">
        {{ $statusCode }}
    </p>

    <x-ui.heading class="text-center" size="xl">{{ $title }}</x-ui.heading>

    <p class="text-center text-sm text-zinc-600">
        {{ $message }}
    </p>

    <x-ui.button variant="primary" class="w-full" :href="url('/')">{{ __('errors.back_home') }}</x-ui.button>

    <x-ui.subheading class="text-center">
        <x-ui.link href="{{ route('auth.login') }}">{{ __('auth.back-to-login') }}</x-ui.link>
    </x-ui.subheading>
</x-layouts.guest>
