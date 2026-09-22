<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\RoleAssignment;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\ActivityObserver;
use App\Policies\ActivityLogPolicy;
use App\Policies\ClassificationPolicy;
use App\Policies\InventoryMovementPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\ProductBatchPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\WarehousePolicy;
use App\Services\ActivityLogger;
use App\Services\ReportQuery;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ActivityLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $identity = is_string($email) ? mb_strtolower(trim($email)) : '';

            return [
                Limit::perMinute(5)->by('account:'.hash('sha256', $identity.'|'.$request->ip())),
                Limit::perMinute(60)->by('ip:'.$request->ip()),
            ];
        });
        foreach (array_keys(ActivityLogger::FIELDS) as $model) {
            $model::observe(ActivityObserver::class);
        }
        RoleAssignment::observe(ActivityObserver::class);
        Gate::policy(ActivityLog::class, ActivityLogPolicy::class);
        Gate::define('view-report', fn (User $user, string $report): bool => $user->is_active && ($report === 'dashboard' || isset(ReportQuery::TYPES[$report]))
                && $user->hasPermission('reports.'.$report));
        Gate::policy(ProductBatch::class, ProductBatchPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(StockAdjustment::class, StockAdjustmentPolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Inventory::class, InventoryPolicy::class);
        Gate::policy(InventoryMovement::class, InventoryMovementPolicy::class);
        foreach ([Category::class, Brand::class, Unit::class] as $model) {
            Gate::policy($model, ClassificationPolicy::class);
        }
    }
}
