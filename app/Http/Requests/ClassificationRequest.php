<?php

namespace App\Http\Requests;

use App\Services\ClassificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClassificationRequest extends FormRequest
{
    public function record(): ?Model
    {
        $model = ClassificationService::modelFor($this->route('resource'));

        return $this->route('record') ? $model::findOrFail($this->route('record')) : null;
    }

    public function authorize(): bool
    {
        $resource = $this->route('resource');
        $record = $this->record();

        return $record
            ? Gate::allows('update', $record)
            : Gate::allows('create', [ClassificationService::modelFor($resource), $resource]);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        if (is_string($this->input('short_name'))) {
            $this->merge(['short_name' => trim($this->input('short_name'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $resource = $this->route('resource');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique($resource, 'name')->ignore($this->record())],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            ...($resource === 'units'
                ? ['short_name' => ['required', 'string', 'max:32']]
                : ['description' => ['nullable', 'string', 'max:5000']]),
        ];
    }
}
