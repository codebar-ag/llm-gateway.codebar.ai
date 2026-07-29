@props(['tone' => 'neutral'])

@php
    $tones = [
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
        'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
        'neutral' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
    ];
@endphp

<span {{ $attributes->class(['rounded-full font-medium', $tones[$tone] ?? $tones['neutral']]) }}>
    {{ $slot }}
</span>
