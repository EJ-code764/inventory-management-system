<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\ProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $this->user()->can('update', $product)
            : $this->user()->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['sku', 'barcode', 'name'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'barcode' && $value === '' ? null : $value]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $product = $this->route('product');
        $stockItem = $product instanceof Product ? $product->stockItem : null;
        $decimal = ['required', 'numeric', 'regex:/^\d{1,16}(\.\d{1,4})?$/'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('stock_items', 'sku')->ignore($stockItem)],
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('stock_items', 'barcode')->ignore($stockItem)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'cost_price' => $decimal,
            'selling_price' => $decimal,
            'reorder_level' => $decimal,
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cost_price.regex' => 'Cost price must be non-negative, with at most 16 whole digits and 4 decimal places.',
            'selling_price.regex' => 'Selling price must be non-negative, with at most 16 whole digits and 4 decimal places.',
            'reorder_level.regex' => 'Reorder level must be non-negative, with at most 16 whole digits and 4 decimal places.',
        ];
    }
}
