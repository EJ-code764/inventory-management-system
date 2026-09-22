<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActivityLogPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'activity-log-viewer'], ['name' => 'Activity Log Viewer']);
            $permission = Permission::firstOrCreate(['slug' => 'activity-logs.view'], ['name' => 'View activity logs']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        });
    }
}
