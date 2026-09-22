<?php

namespace App\Models;

use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (ActivityLog $log): void {
            throw new \LogicException('Activity logs are immutable.');
        });
        static::deleting(function (ActivityLog $log): void {
            throw new \LogicException('Activity logs cannot be deleted through the application.');
        });
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
