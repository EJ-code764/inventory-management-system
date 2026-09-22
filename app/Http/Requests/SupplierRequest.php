<?php

namespace App\Http\Requests;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            ? $this->user()->can('update', $supplier)
            : $this->user()->can('create', Supplier::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['supplier_code', 'name', 'contact_person', 'phone', 'email', 'address'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $optional = ! in_array($field, ['supplier_code', 'name'], true);
                $this->merge([$field => $optional && $value === '' ? null : $value]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'supplier_code' => ['required', 'string', 'max:64', Rule::unique('suppliers', 'supplier_code')->ignore($supplier)],
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+().\s\-x#]+$/i', 'regex:/[0-9]/'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
