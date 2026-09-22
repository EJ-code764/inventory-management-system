<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('purchase_order');

        return $order instanceof PurchaseOrder ? $this->user()->can('update', $order) : $this->user()->can('create', PurchaseOrder::class);
    }

    /** Shared with Livewire and the domain service; totals and statuses are never client inputs.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $decimal = ['required', 'numeric', 'regex:/^\d{1,16}(\.\d{1,4})?$/D'];

        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('status', 'active')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'ordered_at' => ['required', 'date_format:Y-m-d'],
            'expected_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:ordered_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'revision' => ['sometimes', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:stock_item_id,ordered_quantity,unit_cost,discount,tax'],
            'items.*.stock_item_id' => ['required', 'integer', 'distinct', Rule::exists('stock_items', 'id')->where('is_active', true)],
            'items.*.ordered_quantity' => [...$decimal, 'gt:0'],
            'items.*.unit_cost' => $decimal,
            'items.*.discount' => $decimal,
            'items.*.tax' => $decimal,
        ];
    }
}
