@props([
    'title',
    'description' => null,
])

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

    <div>
        <h1 class="page-title">
            {{ $title }}
        </h1>

        @if ($description)
            <p class="page-description">
                {{ $description }}
            </p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset

</div>