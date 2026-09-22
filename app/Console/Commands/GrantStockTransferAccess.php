<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\StockTransferPermissionSeeder;
use Illuminate\Console\Command;

class GrantStockTransferAccess extends Command
{
    protected $signature = 'transfers:grant {email : Existing active user email}';

    protected $description = 'Grant stock transfer management access';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(StockTransferPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'stock-transfer-manager')->sole()->id]);
        $this->info('Stock transfer management access granted.');

        return self::SUCCESS;
    }
}
