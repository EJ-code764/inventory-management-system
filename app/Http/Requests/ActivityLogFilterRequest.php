<?php

namespace App\Http\Requests;

use App\Models\ActivityLog;
use Illuminate\Foundation\Http\FormRequest;

class ActivityLogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ActivityLog::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'], 'user' => ['nullable', 'regex:/^(system|[0-9]+)$/D'],
            'action' => ['nullable', 'string', 'max:255'], 'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
