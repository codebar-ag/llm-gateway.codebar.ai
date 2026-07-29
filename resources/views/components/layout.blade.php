@props(['title' => 'LLM Gateway', 'refresh' => null])

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if ($refresh)
        <meta http-equiv="refresh" content="{{ $refresh }}">
    @endif
    <title>{{ $title }}</title>
    @vite('resources/css/app.css')
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div class="w-full px-4 py-8 sm:px-6 lg:px-8">
    {{ $slot }}
</div>
@stack('scripts')
</body>
</html>
