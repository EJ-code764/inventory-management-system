<?php

namespace App\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'address', 'status', 'is_active'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Warehouse $warehouse): void {
            if ($warehouse->isDirty('is_active') && ! $warehouse->isDirty('status')) {
                $warehouse->status = $warehouse->is_active ? 'active' : 'inactive';
            }
            $warehouse->is_active = $warehouse->status === 'active';
        });
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}
