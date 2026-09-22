<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\WarehousePermissionSeeder;
use Illuminate\Console\Command;

class GrantWarehouseAccess extends Command
{
    protected $signature = 'warehouses:grant {email : Existing active user email}';

    protected $description = 'Grant warehouse management access to an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(WarehousePermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'warehouse-manager')->firstOrFail()->id]);
        $this->info('Warehouse management access granted.');

        return self::SUCCESS;
    }
}
