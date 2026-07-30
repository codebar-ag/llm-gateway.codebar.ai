<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    {{-- Filled per page by Inertia's `title` callback in resources/js/app.js. --}}
    <title inertia>{{ config('app.name', 'LLM Gateway') }}</title>

    <link rel="icon" href="/favicon.svg">

    {{-- Preconnect, because the mono face is a render-blocking webfont: without
         these the browser waits out a DNS lookup and a TLS handshake before it
         can even ask for it. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
{{-- `font-mono` here rather than on each page: it resolves to the design
     system's --font-mono (JetBrains Mono), and Tailwind's preflight gives form
     controls `font: inherit`, so inputs and buttons follow the body instead of
     falling back to the browser's own UI font. --}}
<body class="h-full bg-bg font-mono text-ink antialiased">
@inertia
</body>
</html>
