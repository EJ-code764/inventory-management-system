<x-layouts.app title="Receive purchase order">
    <h1 class="mb-6 text-2xl font-semibold">Receive {{ $order->number }}</h1>
    <livewire:purchase-receiving :order="$order" />
</x-layouts.app>
