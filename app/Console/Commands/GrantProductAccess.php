<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProductPermissionSeeder;
use Illuminate\Console\Command;

class GrantProductAccess extends Command
{
    protected $signature = 'products:grant {email : Existing active user email}';

    protected $description = 'Grant product management access to an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(ProductPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'product-manager')->firstOrFail()->id]);
        $this->info('Product management access granted.');

        return self::SUCCESS;
    }
}
