<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'supplier-manager'], ['name' => 'Supplier Manager']);
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permission = Permission::firstOrCreate(['slug' => 'suppliers.'.$action], ['name' => ucfirst($action).' suppliers']);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
