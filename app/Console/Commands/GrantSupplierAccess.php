<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\SupplierPermissionSeeder;
use Illuminate\Console\Command;

class GrantSupplierAccess extends Command
{
    protected $signature = 'suppliers:grant {email : Existing active user email}';

    protected $description = 'Grant supplier management access to an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(SupplierPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'supplier-manager')->firstOrFail()->id]);
        $this->info('Supplier management access granted.');

        return self::SUCCESS;
    }
}
