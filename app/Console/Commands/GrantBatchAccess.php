<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProductBatchPermissionSeeder;
use Illuminate\Console\Command;

class GrantBatchAccess extends Command
{
    protected $signature = 'batches:grant {email : Existing active user email}';

    protected $description = 'Grant read-only batch and expiration report access';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(ProductBatchPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'batch-viewer')->sole()->id]);
        $this->info('Batch and expiration report access granted.');

        return self::SUCCESS;
    }
}
