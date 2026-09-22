<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PurchasingPermissionSeeder;
use Illuminate\Console\Command;

class GrantPurchasingAccess extends Command
{
    protected $signature = 'purchasing:grant {email : Existing active user email}';

    protected $description = 'Grant purchasing and receiving access to an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(PurchasingPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'purchasing-manager')->firstOrFail()->id]);
        $this->info('Purchasing access granted.');

        return self::SUCCESS;
    }
}
