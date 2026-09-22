<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $fillable = ['name', 'short_name', 'status'];

    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::saving(function (Unit $record): void {
            $record->is_active = $record->status === 'active';
            $record->code ??= (string) Str::uuid();
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
