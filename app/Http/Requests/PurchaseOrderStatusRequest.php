<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->input('status') === 'ordered' ? 'order' : 'cancel', $this->route('purchase_order'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['ordered', 'cancelled'])]];
    }
}
