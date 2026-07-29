<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    {{-- Filled per page by Inertia's `title` callback in resources/js/app.js. --}}
    <title inertia>{{ config('app.name', 'LLM Gateway') }}</title>

    <link rel="icon" href="/favicon.svg">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-bg text-ink antialiased">
@inertia
</body>
</html>
