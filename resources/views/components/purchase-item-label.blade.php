@props(['item'])
@if($item)
    <span class="font-medium">{{ $item->sku }}</span>
    <span class="block text-sm text-slate-600">{{ $item->product?->name ?? $item->variant?->product?->name }}{{ $item->variant ? ' — '.$item->variant->name : '' }}</span>
@else
    <span>Unavailable stock item</span>
@endif
