<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@inventory.test',
                'role' => 'admin',
            ],
            [
                'name' => 'Store Manager',
                'email' => 'manager@inventory.test',
                'role' => 'manager',
            ],
            [
                'name' => 'Store Staff',
                'email' => 'staff@inventory.test',
                'role' => 'staff',
            ],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'role' => 'admin',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password'),
                    'role' => $user['role'],
                ]
            );
        }
    }
}