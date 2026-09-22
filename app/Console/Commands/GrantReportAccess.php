<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ReportPermissionSeeder;
use Illuminate\Console\Command;

class GrantReportAccess extends Command
{
    protected $signature = 'reports:grant {email : Existing active user email}';

    protected $description = 'Grant read-only dashboard and report access';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(ReportPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'report-viewer')->sole()->id]);
        $this->info('Dashboard and report access granted.');

        return self::SUCCESS;
    }
}
