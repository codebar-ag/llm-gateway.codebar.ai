<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>{{ config('app.name') }}</title>

@include('layouts._partials.seo._favicons')
@include('layouts._partials.analytics._fathom')
@include('layouts._partials.analytics._userback')

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link rel="dns-prefetch" href="https://fonts.bunny.net">
<link rel="preload" href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" as="style">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" media="print"
    onload="this.media='all'">
<noscript><link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet"></noscript>

@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/userback.js'])
