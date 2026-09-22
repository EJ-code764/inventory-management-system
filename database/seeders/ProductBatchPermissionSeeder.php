<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductBatchPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'batch-viewer'], ['name' => 'Batch and Expiration Viewer']);
            $permission = Permission::firstOrCreate(['slug' => 'batches.view'], ['name' => 'View batches and expiration reports']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        });
    }
}
