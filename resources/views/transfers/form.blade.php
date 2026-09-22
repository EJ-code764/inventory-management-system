<x-layouts.app :title="$transfer ? 'Edit stock transfer' : 'New stock transfer'">
    <h1 class="mb-6 text-2xl font-semibold">{{ $transfer ? 'Edit '.$transfer->number : 'New stock transfer' }}</h1>
    <livewire:stock-transfer-form :transfer="$transfer" />
</x-layouts.app>
