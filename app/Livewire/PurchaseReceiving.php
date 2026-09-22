<?php

namespace App\Livewire;

use App\Models\PurchaseOrder;
use App\PurchaseOrderStatus;
use App\Services\ReceivingService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PurchaseReceiving extends Component
{
    #[Locked]
    public int $orderId;

    #[Locked]
    public string $idempotencyKey;

    public array $quantities = [];

    public array $batchNumbers = [];

    public array $expirationDates = [];

    public string $notes = '';

    public function mount(PurchaseOrder $order): void
    {
        $order = PurchaseOrder::findOrFail($order->id);
        Gate::authorize('receive', $order);
        abort_unless(in_array($order->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true), 409, 'This order is not available for receiving.');
        $this->orderId = $order->id;
        $this->idempotencyKey = (string) Str::uuid();
        foreach ($order->items as $item) {
            $this->quantities[$item->id] = '';
        }
    }

    public function receive(): void
    {
        $this->resetValidation();
        $order = PurchaseOrder::findOrFail($this->orderId);
        Gate::authorize('receive', $order);
        $lines = [];
        foreach ($this->quantities as $id => $quantity) {
            if ($quantity === '' || $quantity === null) {
                continue;
            }
            $lines[] = [
                'purchase_order_item_id' => $id, 'quantity' => $quantity,
                'batch_number' => ($this->batchNumbers[$id] ?? '') === '' ? null : $this->batchNumbers[$id],
                'expiration_date' => ($this->expirationDates[$id] ?? '') === '' ? null : $this->expirationDates[$id],
            ];
        }
        try {
            $receipt = app(ReceivingService::class)->receive($order, ['items' => $lines, 'notes' => $this->notes, 'idempotency_key' => $this->idempotencyKey], auth()->user());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }
        session()->flash('status', 'Receipt recorded. Inventory and PURCHASE movements were updated.');
        $this->redirectRoute('purchase-orders.receipt', [$order, $receipt]);
    }

    public function render(): View
    {
        $order = PurchaseOrder::with(['warehouse', 'items.stockItem.product', 'items.stockItem.variant.product'])->findOrFail($this->orderId);
        Gate::authorize('receive', $order);

        return view('livewire.purchase-receiving', compact('order'));
    }
}
