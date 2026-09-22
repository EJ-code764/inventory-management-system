<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchasingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'purchasing-manager'], ['name' => 'Purchasing Manager']);
            foreach (['view', 'create', 'update', 'order', 'cancel', 'receive'] as $action) {
                $permission = Permission::firstOrCreate(['slug' => 'purchasing.'.$action], ['name' => ucfirst($action).' purchasing']);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
