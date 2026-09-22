<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassificationService
{
    /** @return class-string<Category|Brand|Unit> */
    public static function modelFor(string $resource): string
    {
        return match ($resource) {
            'categories' => Category::class,
            'brands' => Brand::class,
            'units' => Unit::class,
            default => abort(404),
        };
    }

    public function delete(Model $record): void
    {
        DB::transaction(function () use ($record): void {
            $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            if ($locked->products()->exists() || ($locked instanceof Category && Category::where('parent_id', $locked->id)->exists())) {
                throw ValidationException::withMessages(['record' => 'This record is in use. Deactivate it instead of deleting it.']);
            }
            $locked->delete();
        });
    }
}
