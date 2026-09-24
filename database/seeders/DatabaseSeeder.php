<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,

            CategorySeeder::class,
            BrandSeeder::class,
            UnitSeeder::class,
            WarehouseSeeder::class,
            SupplierSeeder::class,

            ProductSeeder::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Development-only sample accounts
        |--------------------------------------------------------------------------
        |
        | Never seed sample credentials into production or staging.
        |
        */

        if (app()->environment('local')) {
            $this->call(UserSeeder::class);
        }
    }
}