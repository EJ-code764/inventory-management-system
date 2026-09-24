@props([
    'padding' => true,
])

<div
    {{ $attributes->class([
        'rounded-xl border border-slate-200 bg-white shadow-sm',
        'p-5' => $padding,
    ]) }}
>
    {{ $slot }}
</div>