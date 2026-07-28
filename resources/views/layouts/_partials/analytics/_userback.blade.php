@props([])

@php
    $environments = config('default.services.userback.environments', []);
    $url = config('default.services.userback.url');
    $token = config('default.services.userback.token');
@endphp

@if (in_array(app()->environment(), $environments) && $token)
    {{-- Userback configuration for lazy loading via JavaScript --}}
    <script type="application/json" data-userback-data>
        {
            "token": "{{ $token }}",
            "url": "{{ $url }}",
            "userData": {
                "id": "{{ auth()->user()?->id }}",
                "info": {
                    "name": "{{ auth()->user()?->name }}",
                    "email": "{{ auth()->user()?->email }}"
                }
            }
        }
    </script>
@endif
