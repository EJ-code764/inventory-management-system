<?php

namespace App\Http\Requests;

use App\Models\StockTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transfer = $this->route('stock_transfer');

        return $transfer instanceof StockTransfer ? $this->user()->can('update', $transfer) : $this->user()->can('create', StockTransfer::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'transfer_date' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'revision' => ['sometimes', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:stock_item_id,quantity'],
            'items.*.stock_item_id' => ['required', 'integer', 'distinct', Rule::exists('stock_items', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'numeric', 'regex:/^\d{1,16}(\.\d{1,4})?$/D', 'gt:0'],
        ];
    }
}
