<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassificationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'classification-manager'], ['name' => 'Classification Manager']);
            foreach (['categories', 'brands', 'units'] as $resource) {
                foreach (['view', 'create', 'update', 'delete'] as $action) {
                    $permission = Permission::firstOrCreate(['slug' => $resource.'.'.$action], ['name' => ucfirst($action).' '.$resource]);
                    $role->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        });
    }
}
