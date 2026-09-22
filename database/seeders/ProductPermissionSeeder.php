<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'product-manager'], ['name' => 'Product Manager']);
            foreach (['view', 'create', 'update'] as $action) {
                $permission = Permission::firstOrCreate(['slug' => 'products.'.$action], ['name' => ucfirst($action).' products']);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
