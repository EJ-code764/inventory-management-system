<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->input('status') === 'completed' ? 'complete' : 'cancel', $this->route('stock_transfer'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['completed', 'cancelled'])], 'revision' => ['required', 'integer', 'min:1']];
    }
}
