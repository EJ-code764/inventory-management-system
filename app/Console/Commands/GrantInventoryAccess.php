<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\InventoryPermissionSeeder;
use Illuminate\Console\Command;

class GrantInventoryAccess extends Command
{
    protected $signature = 'inventory:grant {email : Existing active user email}';

    protected $description = 'Grant read-only inventory and movement history access';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(InventoryPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'inventory-viewer')->firstOrFail()->id]);
        $this->info('Inventory viewing access granted.');

        return self::SUCCESS;
    }
}
