<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockTransferPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'stock-transfer-manager'], ['name' => 'Stock Transfer Manager']);
            foreach (['view', 'create', 'update', 'complete', 'cancel'] as $action) {
                $permission = Permission::firstOrCreate(['slug' => 'transfers.'.$action], ['name' => ucfirst($action).' stock transfers']);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
