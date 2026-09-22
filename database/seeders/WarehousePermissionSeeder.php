<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehousePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'warehouse-manager'], ['name' => 'Warehouse Manager']);
            foreach (['view', 'create', 'update'] as $action) {
                $permission = Permission::firstOrCreate(['slug' => 'warehouses.'.$action], ['name' => ucfirst($action).' warehouses']);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
