<?php

namespace App\Livewire;

use App\Http\Requests\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\PurchaseOrderStatus;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseTotals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PurchaseOrderForm extends Component
{
    #[Locked]
    public ?int $orderId = null;

    #[Locked]
    public int $revision = 1;

    public array $form = ['supplier_id' => '', 'warehouse_id' => '', 'ordered_at' => '', 'expected_at' => '', 'notes' => '', 'items' => []];

    public string $search = '';

    public function mount(?PurchaseOrder $order = null): void
    {
        $this->orderId = $order?->id;
        $this->authorizeForm();
        $this->form['ordered_at'] = today()->format('Y-m-d');
        if ($this->orderId) {
            $order = PurchaseOrder::findOrFail($this->orderId);
            abort_unless($order->status === PurchaseOrderStatus::Draft, 409, 'Only draft orders can be edited.');
            $this->revision = $order->revision;
            $this->form = [
                'supplier_id' => $order->supplier_id, 'warehouse_id' => $order->warehouse_id,
                'ordered_at' => $order->ordered_at?->format('Y-m-d'), 'expected_at' => $order->expected_at?->format('Y-m-d') ?? '',
                'notes' => $order->notes ?? '',
                'items' => $order->items->map->only(['stock_item_id', 'ordered_quantity', 'unit_cost', 'discount', 'tax'])->all(),
            ];
        }
    }

    public function addItem(int $id): void
    {
        $this->authorizeForm();
        if (count($this->form['items']) >= 100) {
            $this->addError('form.items', 'A purchase order can contain at most 100 items.');

            return;
        }
        foreach ($this->form['items'] as $line) {
            if ((int) $line['stock_item_id'] === $id) {
                $this->addError('form.items', 'This SKU is already on the order. Change its quantity instead.');

                return;
            }
        }
        $item = app(PurchaseOrderService::class)->activeStockItem($id);
        $this->form['items'][] = ['stock_item_id' => $item->id, 'ordered_quantity' => '1', 'unit_cost' => $item->cost_price, 'discount' => '0', 'tax' => '0'];
        $this->search = '';
        $this->resetValidation();
    }

    public function removeItem(int $index): void
    {
        $this->authorizeForm();
        unset($this->form['items'][$index]);
        $this->form['items'] = array_values($this->form['items']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeForm();
        $rules = collect((new PurchaseOrderRequest)->rules())->mapWithKeys(fn (mixed $rules, string $key): array => ['form.'.$key => $rules])->all();
        $rules['form.expected_at'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:form.ordered_at'];
        $data = $this->validate($rules)['form'];
        $data['expected_at'] = $data['expected_at'] ?: null;
        try {
            $order = app(PurchaseOrderService::class)->saveDraft([...$data, 'revision' => $this->revision], auth()->user(), $this->orderId ? PurchaseOrder::findOrFail($this->orderId) : null);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

            return;
        }
        session()->flash('status', 'Purchase order draft saved. Inventory has not changed.');
        $this->redirectRoute('purchase-orders.show', $order);
    }

    protected function authorizeForm(): void
    {
        Gate::authorize($this->orderId ? 'update' : 'create', $this->orderId ? PurchaseOrder::findOrFail($this->orderId) : PurchaseOrder::class);
    }

    public function render(): View
    {
        $this->authorizeForm();
        $search = mb_substr(trim($this->search), 0, 255);
        $matches = StockItem::with(['product', 'variant.product'])->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereHas('product', fn (Builder $product): Builder => $product->where('status', 'active')->where('has_variants', false))
                    ->orWhereHas('variant', fn (Builder $variant): Builder => $variant->where('is_active', true)->whereHas('product', fn (Builder $product): Builder => $product->where('status', 'active')));
            })
            ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('sku', 'like', '%'.$search.'%')->orWhere('barcode', 'like', '%'.$search.'%')
                    ->orWhereHas('product', fn (Builder $product): Builder => $product->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('variant', fn (Builder $variant): Builder => $variant->where('name', 'like', '%'.$search.'%')->orWhereHas('product', fn (Builder $product): Builder => $product->where('name', 'like', '%'.$search.'%')));
            }))->orderBy('sku')->limit(15)->get();
        $stockItems = StockItem::with(['product', 'variant.product'])->whereIn('id', collect($this->form['items'])->pluck('stock_item_id')->filter(fn (mixed $id): bool => is_scalar($id))->all())->get()->keyBy('id');
        $totals = null;
        $rules = Arr::where((new PurchaseOrderRequest)->rules(), fn (mixed $rule, string $key): bool => $key === 'items' || str_starts_with($key, 'items.'));
        if (! Validator::make(['items' => $this->form['items']], $rules)->fails()) {
            try {
                $totals = app(PurchaseTotals::class)->calculate($this->form['items']);
            } catch (ValidationException) {
                $totals = null;
            }
        }

        return view('livewire.purchase-order-form', [
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'matches' => $matches, 'stockItems' => $stockItems, 'totals' => $totals,
        ]);
    }
}
