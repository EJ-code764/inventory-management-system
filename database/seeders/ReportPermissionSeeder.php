<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\ReportQuery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportPermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'report-viewer'], ['name' => 'Dashboard and Reports Viewer']);
            foreach (['dashboard' => 'Inventory dashboard', ...ReportQuery::TYPES] as $key => $label) {
                $permission = Permission::firstOrCreate(['slug' => 'reports.'.$key], ['name' => 'View '.$label]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
