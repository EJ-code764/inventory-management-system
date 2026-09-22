<?php

namespace App\Http\Requests;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            ? $this->user()->can('update', $warehouse)
            : $this->user()->can('create', Warehouse::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['code', 'name', 'address'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'address' && $value === '' ? null : $value]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('warehouses', 'code')->ignore($warehouse)],
            'name' => ['required', 'string', 'max:255', Rule::unique('warehouses', 'name')->ignore($warehouse)],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
