@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'primary' => '
            bg-primary-50 text-primary-700
            dark:bg-primary-950/50 dark:text-primary-300
        ',

        'success' => '
            bg-emerald-50 text-emerald-700
            dark:bg-emerald-950/50 dark:text-emerald-300
        ',

        'warning' => '
            bg-amber-50 text-amber-700
            dark:bg-amber-950/50 dark:text-amber-300
        ',

        'danger' => '
            bg-red-50 text-red-700
            dark:bg-red-950/50 dark:text-red-300
        ',

        'neutral' => '
            bg-slate-100 text-slate-700
            dark:bg-slate-800 dark:text-slate-300
        ',
    ];
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
        $variants[$variant] ?? $variants['neutral'],
    ]) }}
>
    {{ $slot }}
</span>