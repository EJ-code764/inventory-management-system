<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'status'];

    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::saving(function (Category $record): void {
            $record->is_active = $record->status === 'active';
            $record->slug ??= (string) Str::uuid();
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
