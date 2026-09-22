<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\StockAdjustmentPermissionSeeder;
use Illuminate\Console\Command;

class GrantStockAdjustmentAccess extends Command
{
    protected $signature = 'adjustments:grant {email : Existing active user email}';

    protected $description = 'Grant stock adjustment creation and history access';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(StockAdjustmentPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'stock-adjustment-manager')->sole()->id]);
        $this->info('Stock adjustment access granted.');

        return self::SUCCESS;
    }
}
