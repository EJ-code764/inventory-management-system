<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'inventory-viewer'], ['name' => 'Inventory Viewer']);
            foreach (['inventory.view' => 'View inventory', 'inventory.movements.view' => 'View inventory movements'] as $slug => $name) {
                $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
