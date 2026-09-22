<x-layouts.app :title="$order ? 'Edit purchase order' : 'New purchase order'">
    <h1 class="mb-6 text-2xl font-semibold">{{ $order ? 'Edit '.$order->number : 'New purchase order' }}</h1>
    <livewire:purchase-order-form :order="$order" />
</x-layouts.app>
