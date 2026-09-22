<?php

namespace App\Observers;

use App\Models;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityObserver
{
    public function created(Model $model): void
    {
        $this->write($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->write($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted');
    }

    private function write(Model $model, string $operation): void
    {
        $logger = app(ActivityLogger::class);
        if ($model instanceof Models\RoleAssignment) {
            $user = Models\User::find($model->user_id);
            if ($user) {
                $values = ['role_id' => $model->role_id];
                $logger->record($operation === 'deleted' ? 'user.role_removed' : 'user.role_assigned', $user, $operation === 'deleted' ? $values : [], $operation === 'deleted' ? [] : $values);
            }

            return;
        }
        $fields = ActivityLogger::FIELDS[$model::class] ?? [];
        $old = $operation === 'created' ? [] : Arr::only($model->getRawOriginal(), $fields);
        $new = $operation === 'deleted' ? [] : Arr::only($model->getAttributes(), $fields);
        $event = Str::snake(class_basename($model)).'.'.$operation;
        if ($operation === 'updated') {
            $changes = Arr::only($model->getChanges(), $fields);
            $old = Arr::only($old, array_keys($changes));
            $new = Arr::only($new, array_keys($changes));
            if ($model instanceof Models\User) {
                if ($model->wasChanged('email')) {
                    $new['email_changed'] = true;
                }
                if ($model->wasChanged('password')) {
                    $new['credentials_changed'] = true;
                }
            }
            if ($old === [] && $new === []) {
                return;
            }
            if ($model instanceof Models\Product && $model->wasChanged('status')) {
                $event = $model->getRawOriginal('status') === 'active' ? 'product.deactivated' : 'product.activated';
            }
        }
        if ($operation === 'created' && $model instanceof Models\PurchaseReceipt) {
            $event = 'purchase.received';
        }
        if ($operation === 'created' && $model instanceof Models\InventoryMovement) {
            $event = 'inventory.changed';
            $old = ['quantity' => $model->quantity_before];
            $new['quantity'] = $model->quantity_after;
        }
        if ($operation === 'created' && $model instanceof Models\StockAdjustmentItem) {
            $old = ['quantity' => $model->previous_quantity];
            $new['quantity'] = $model->new_quantity;
        }
        $logger->record($event, $model, $old, $new);
    }
}
