<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClassificationPermissionSeeder;
use Illuminate\Console\Command;

class GrantClassificationAccess extends Command
{
    protected $signature = 'classification:grant {email : Existing user email}';

    protected $description = 'Grant classification management permissions to an existing active user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->where('is_active', true)->first();
        if (! $user) {
            $this->error('No active user found for that email.');

            return self::FAILURE;
        }
        app(ClassificationPermissionSeeder::class)->run();
        $user->roles()->syncWithoutDetaching([Role::where('slug', 'classification-manager')->firstOrFail()->id]);
        $this->info('Classification management access granted.');

        return self::SUCCESS;
    }
}
