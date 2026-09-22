<?php

namespace App\Livewire;

use App\Models\Inventory;
use App\Models\StockAdjustment;
use App\Models\StockItem;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StockAdjustmentForm extends Component
{
    public string $warehouseId = '';

    public string $search = '';

    public string $newQuantity = '';

    public string $reason = '';

    #[Locked]
    public ?int $stockItemId = null;

    #[Locked]
    public ?int $previewWarehouseId = null;

    #[Locked]
    public ?string $currentQuantity = null;

    #[Locked]
    public string $reservedQuantity = '0.0000';

    #[Locked]
    public string $submissionKey = '';

    public function mount(): void
    {
        Gate::authorize('create', StockAdjustment::class);
        $this->submissionKey = (string) Str::uuid();
    }

    public function updatedWarehouseId(): void
    {
        Gate::authorize('create', StockAdjustment::class);
        $this->reset(['stockItemId', 'previewWarehouseId', 'currentQuantity', 'reservedQuantity', 'newQuantity']);
        $this->resetValidation();
        $this->submissionKey = (string) Str::uuid();
    }

    public function selectItem(int $id): void
    {
        Gate::authorize('create', StockAdjustment::class);
        StockItem::findOrFail($id);
        $this->stockItemId = $id;
        $this->refreshStock();
    }

    public function refreshStock(): void
    {
        Gate::authorize('create', StockAdjustment::class);
        Validator::make(['warehouse_id' => $this->warehouseId, 'stock_item_id' => $this->stockItemId], [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id')->where('is_active', true)],
        ])->validate();
        $inventory = Inventory::where('warehouse_id', $this->warehouseId)->where('stock_item_id', $this->stockItemId)->first();
        $this->previewWarehouseId = (int) $this->warehouseId;
        $this->currentQuantity = $inventory?->quantity ?? '0.0000';
        $this->reservedQuantity = $inventory?->reserved_quantity ?? '0.0000';
        $this->newQuantity = '';
        $this->submissionKey = (string) Str::uuid();
        $this->resetValidation();
    }

    public function save(): void
    {
        Gate::authorize('create', StockAdjustment::class);
        $this->resetValidation();
        if ($this->currentQuantity === null || (int) $this->warehouseId !== $this->previewWarehouseId) {
            $this->addError('expected_quantity', 'Select a warehouse and product to review current stock first.');

            return;
        }
        try {
            $adjustment = app(StockAdjustmentService::class)->adjust([
                'warehouse_id' => $this->warehouseId, 'stock_item_id' => $this->stockItemId,
                'expected_quantity' => $this->currentQuantity, 'new_quantity' => $this->newQuantity,
                'reason' => $this->reason, 'idempotency_key' => $this->submissionKey,
            ], auth()->user());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }
        session()->flash('status', 'Stock adjustment recorded with an inventory movement.');
        $this->redirectRoute('stock-adjustments.show', $adjustment);
    }

    public function render(): View
    {
        Gate::authorize('create', StockAdjustment::class);
        $search = mb_substr(trim($this->search), 0, 255);
        $matches = StockItem::with(['product', 'variant.product'])->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereHas('product', fn (Builder $product): Builder => $product->where('status', 'active')->where('has_variants', false))
                    ->orWhereHas('variant', fn (Builder $variant): Builder => $variant->where('is_active', true)->whereHas('product', fn (Builder $product): Builder => $product->where('status', 'active')));
            })->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('sku', 'like', '%'.$search.'%')->orWhere('barcode', 'like', '%'.$search.'%')
                    ->orWhereHas('product', fn (Builder $product): Builder => $product->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('variant', fn (Builder $variant): Builder => $variant->where('name', 'like', '%'.$search.'%')->orWhereHas('product', fn (Builder $product): Builder => $product->where('name', 'like', '%'.$search.'%')));
            }))->orderBy('sku')->limit(15)->get();
        $difference = null;
        if ($this->currentQuantity !== null && preg_match('/^\d{1,16}(\.\d{1,4})?$/D', $this->newQuantity)) {
            $difference = (string) BigDecimal::of($this->newQuantity)->minus($this->currentQuantity)->toScale(4);
        }

        return view('livewire.stock-adjustment-form', [
            'matches' => $matches, 'difference' => $difference,
            'selectedItem' => $this->stockItemId ? StockItem::with(['product', 'variant.product'])->find($this->stockItemId) : null,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
