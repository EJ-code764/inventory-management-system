@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'help' => null,
])

<div>
    @if ($label)
        <label
            @if ($name) for="{{ $name }}" @endif
            class="form-label"
        >
            {{ $label }}
        </label>
    @endif

    <input
        @if ($name) id="{{ $name }}" name="{{ $name }}" @endif
        type="{{ $type }}"
        {{ $attributes->class([
            'form-input',
            'border-red-300 focus:border-red-500 focus:ring-red-500/20' => $error,
        ]) }}
    >

    @if ($error)
        <p class="mt-1 text-sm text-red-600">
            {{ $error }}
        </p>
    @elseif ($help)
        <p class="form-help">
            {{ $help }}
        </p>
    @endif
</div>