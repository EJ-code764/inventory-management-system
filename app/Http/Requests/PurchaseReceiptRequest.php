<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('purchase_order'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array:purchase_order_item_id,quantity,batch_number,expiration_date'],
            'items.*.batch_number' => ['nullable', 'required_with:items.*.expiration_date', 'string', 'max:255', 'regex:/\S/u'],
            'items.*.expiration_date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'regex:/^\d{1,16}(\.\d{1,4})?$/D', 'gt:0'],
        ];
    }
}
