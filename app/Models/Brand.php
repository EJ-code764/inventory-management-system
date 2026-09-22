<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'status'];

    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::saving(function (Brand $record): void {
            $record->is_active = $record->status === 'active';
            $record->slug ??= (string) Str::uuid();
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
