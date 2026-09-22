<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-report', $this->route('report')) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'warehouse' => ['nullable', 'integer', 'exists:warehouses,id'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'period' => ['required', 'in:all,7,30,expired'],
        ];
    }
}
