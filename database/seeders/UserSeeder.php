<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@inventory.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Store Manager',
            'email' => 'manager@inventory.test',
            'password' => Hash::make('password'),
            'role' => 'manager',
        ]);

        User::create([
            'name' => 'Store Staff',
            'email' => 'staff@inventory.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);
    }
}