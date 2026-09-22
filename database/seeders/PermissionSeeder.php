<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Categories
            ['name' => 'View Categories', 'slug' => 'categories.view'],
            ['name' => 'Create Categories', 'slug' => 'categories.create'],
            ['name' => 'Update Categories', 'slug' => 'categories.update'],
            ['name' => 'Delete Categories', 'slug' => 'categories.delete'],

            // Brands
            ['name' => 'View Brands', 'slug' => 'brands.view'],
            ['name' => 'Create Brands', 'slug' => 'brands.create'],
            ['name' => 'Update Brands', 'slug' => 'brands.update'],
            ['name' => 'Delete Brands', 'slug' => 'brands.delete'],

            // Units
            ['name' => 'View Units', 'slug' => 'units.view'],
            ['name' => 'Create Units', 'slug' => 'units.create'],
            ['name' => 'Update Units', 'slug' => 'units.update'],
            ['name' => 'Delete Units', 'slug' => 'units.delete'],
            // Products
            ['name' => 'View Products', 'slug' => 'products.view'],
            ['name' => 'Create Products', 'slug' => 'products.create'],
            ['name' => 'Update Products', 'slug' => 'products.update'],
            ['name' => 'Delete Products', 'slug' => 'products.delete'],

            // Suppliers
            ['name' => 'View Suppliers', 'slug' => 'suppliers.view'],
            ['name' => 'Create Suppliers', 'slug' => 'suppliers.create'],
            ['name' => 'Update Suppliers', 'slug' => 'suppliers.update'],
            ['name' => 'Delete Suppliers', 'slug' => 'suppliers.delete'],

            // Warehouses
            ['name' => 'View Warehouses', 'slug' => 'warehouses.view'],
            ['name' => 'Create Warehouses', 'slug' => 'warehouses.create'],
            ['name' => 'Update Warehouses', 'slug' => 'warehouses.update'],
            ['name' => 'Delete Warehouses', 'slug' => 'warehouses.delete'],

            // Inventory
            ['name' => 'View Inventory', 'slug' => 'inventory.view'],
            ['name' => 'View Inventory Movements', 'slug' => 'inventory.movements.view'],

            // Purchasing
            ['name' => 'View Purchasing', 'slug' => 'purchasing.view'],
            ['name' => 'Create Purchase Orders', 'slug' => 'purchasing.create'],
            ['name' => 'Update Purchase Orders', 'slug' => 'purchasing.update'],
            ['name' => 'Order Purchase Orders', 'slug' => 'purchasing.order'],
            ['name' => 'Cancel Purchase Orders', 'slug' => 'purchasing.cancel'],
            ['name' => 'Receive Purchase Orders', 'slug' => 'purchasing.receive'],

            // Stock Adjustments
            ['name' => 'View Stock Adjustments', 'slug' => 'adjustments.view'],
            ['name' => 'Create Stock Adjustments', 'slug' => 'adjustments.create'],

            // Stock Transfers
            ['name' => 'View Stock Transfers', 'slug' => 'transfers.view'],
            ['name' => 'Create Stock Transfers', 'slug' => 'transfers.create'],
            ['name' => 'Update Stock Transfers', 'slug' => 'transfers.update'],
            ['name' => 'Complete Stock Transfers', 'slug' => 'transfers.complete'],
            ['name' => 'Cancel Stock Transfers', 'slug' => 'transfers.cancel'],

            // Product Batches
            ['name' => 'View Product Batches', 'slug' => 'batches.view'],

            // Activity Logs
            ['name' => 'View Activity Logs', 'slug' => 'activity-logs.view'],

            // Reports
            ['name' => 'View Inventory Reports', 'slug' => 'reports.inventory'],
            ['name' => 'View Stock Movement Reports', 'slug' => 'reports.movements'],
            ['name' => 'View Purchasing Reports', 'slug' => 'reports.purchasing'],

            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'reports.dashboard'],

            // Reports
            ['name' => 'View Inventory Report', 'slug' => 'reports.inventory'],
            ['name' => 'View Inventory Valuation', 'slug' => 'reports.valuation'],
            ['name' => 'View Stock Movement Report', 'slug' => 'reports.movements'],
            ['name' => 'View Low Stock Report', 'slug' => 'reports.low-stock'],
            ['name' => 'View Expiration Report', 'slug' => 'reports.expiration'],
            ['name' => 'View Purchase Report', 'slug' => 'reports.purchases'],
            ['name' => 'View Stock Adjustment Report', 'slug' => 'reports.adjustments'],
            ['name' => 'View Stock Transfer Report', 'slug' => 'reports.transfers'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                ['name' => $permission['name']]
            );
        }

        // Give Administrator every permission
        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole) {
            $adminRole->permissions()->sync(
                Permission::pluck('id')->all()
            );
        }
    }
}