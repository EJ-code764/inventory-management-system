<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Authorization
            PermissionSeeder::class,

            // Inventory master data
            CategorySeeder::class,
            BrandSeeder::class,
            UnitSeeder::class,
            WarehouseSeeder::class,
            SupplierSeeder::class,

            // Products depend on the data above
            ProductSeeder::class,
        ]);
    }
}