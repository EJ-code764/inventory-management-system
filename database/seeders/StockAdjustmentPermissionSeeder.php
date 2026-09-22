<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockAdjustmentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'stock-adjustment-manager'], ['name' => 'Stock Adjustment Manager']);
            foreach (['view' => 'View stock adjustments', 'create' => 'Create stock adjustments'] as $action => $name) {
                $permission = Permission::firstOrCreate(['slug' => 'adjustments.'.$action], ['name' => $name]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
