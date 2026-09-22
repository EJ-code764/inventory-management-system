<?php

namespace App\Livewire;

use App\Http\Requests\StockTransferRequest;
use App\Models\Inventory;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use App\StockTransferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StockTransferForm extends Component
{
    #[Locked]
    public ?int $transferId = null;

    #[Locked]
    public int $revision = 1;

    public array $form = ['source_warehouse_id' => '', 'destination_warehouse_id' => '', 'transfer_date' => '', 'remarks' => '', 'items' => []];

    public string $search = '';

    public function mount(?StockTransfer $transfer = null): void
    {
        $this->transferId = $transfer?->id;
        $this->authorizeForm();
        $this->form['transfer_date'] = today()->format('Y-m-d');
        if ($this->transferId) {
            $transfer = StockTransfer::findOrFail($this->transferId);
            abort_unless($transfer->status === StockTransferStatus::Draft, 409, 'Only draft transfers can be edited.');
            $this->revision = $transfer->revision;
            $this->form = [
                'source_warehouse_id' => $transfer->source_warehouse_id, 'destination_warehouse_id' => $transfer->destination_warehouse_id,
                'transfer_date' => $transfer->transfer_date?->format('Y-m-d') ?? '', 'remarks' => $transfer->remarks ?? '',
                'items' => $transfer->items->map->only(['stock_item_id', 'quantity'])->all(),
            ];
        }
    }

    public function addItem(int $id): void
    {
        $this->authorizeForm();
        if (count($this->form['items']) >= 100) {
            $this->addError('form.items', 'A transfer can contain at most 100 items.');

            return;
        }
        if (collect($this->form['items'])->contains(fn (array $line): bool => (int) $line['stock_item_id'] === $id)) {
            $this->addError('form.items', 'This SKU is already listed. Change its quantity instead.');

            return;
        }
        StockItem::where('is_active', true)->findOrFail($id);
        $this->form['items'][] = ['stock_item_id' => $id, 'quantity' => '1'];
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
        $rules = collect((new StockTransferRequest)->rules())->mapWithKeys(fn (mixed $rules, string $key): array => ['form.'.$key => $rules])->all();
        $rules['form.destination_warehouse_id'][2] = 'different:form.source_warehouse_id';
        $data = $this->validate($rules)['form'];
        try {
            $transfer = app(StockTransferService::class)->saveDraft([...$data, 'revision' => $this->revision], auth()->user(), $this->transferId ? StockTransfer::findOrFail($this->transferId) : null);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

            return;
        }
        session()->flash('status', 'Transfer draft saved. Inventory has not changed.');
        $this->redirectRoute('stock-transfers.show', $transfer);
    }

    protected function authorizeForm(): void
    {
        Gate::authorize($this->transferId ? 'update' : 'create', $this->transferId ? StockTransfer::findOrFail($this->transferId) : StockTransfer::class);
    }

    public function render(): View
    {
        $this->authorizeForm();
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
        $ids = collect($this->form['items'])->pluck('stock_item_id')->filter(fn (mixed $id): bool => is_scalar($id))->all();
        $stockItems = StockItem::with(['product', 'variant.product'])->whereIn('id', $ids)->get()->keyBy('id');
        $balances = Inventory::where('warehouse_id', is_scalar($this->form['source_warehouse_id']) ? $this->form['source_warehouse_id'] : 0)
            ->whereIn('stock_item_id', array_merge($ids, $matches->pluck('id')->all()))->get()->keyBy('stock_item_id');

        return view('livewire.stock-transfer-form', [
            'matches' => $matches, 'stockItems' => $stockItems, 'balances' => $balances,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
