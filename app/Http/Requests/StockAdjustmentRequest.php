<?php

namespace App\Http\Requests;

use App\Models\StockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockAdjustment::class);
    }

    /** Shared by the Livewire form and domain service.
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $decimal = ['required', 'numeric', 'regex:/^\d{1,16}(\.\d{1,4})?$/D'];

        return [
            'stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'expected_quantity' => $decimal,
            'new_quantity' => $decimal,
            'reason' => ['required', 'string', 'max:255', 'regex:/\S/u'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
