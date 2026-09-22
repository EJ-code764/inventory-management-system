<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ActivityLogPermissionSeeder;
use Illuminate\Console\Command;

class GrantActivityLogAccess extends Command
{
    protected $signature = 'activity:grant {email : Existing active user email}';

    protected $description = 'Grant read-only activity log access';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(ActivityLogPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'activity-log-viewer')->sole()->id]);
        $this->info('Activity log access granted.');

        return self::SUCCESS;
    }
}
