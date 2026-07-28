@props([])

@php
    use Illuminate\Support\Arr;

    $environments = config('default.services.fathom.environments', []);
    $url = config('default.services.fathom.url');
    $siteId = config('default.services.fathom.site_id');
@endphp

@if (in_array(app()->environment(), $environments) && $siteId)
    <script src="{{ $url }}" data-site="{{ $siteId }}" defer></script>
@endif
