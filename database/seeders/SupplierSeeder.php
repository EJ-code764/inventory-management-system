<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'supplier_code' => 'SUP-001',
                'name' => 'Davao Wholesale Trading',
                'contact_person' => 'Ana Cruz',
                'email' => 'ana@davaowholesale.test',
                'phone' => '09171234567',
                'address' => 'Davao City',
                'is_active' => true,
                'status' => 'active',
            ],
            [
                'supplier_code' => 'SUP-002',
                'name' => 'Mindanao Food Supply',
                'contact_person' => 'Mark Santos',
                'email' => 'mark@mindanaofood.test',
                'phone' => '09181234567',
                'address' => 'Kidapawan City, Cotabato',
                'is_active' => true,
                'status' => 'active',
            ],
            [
                'supplier_code' => 'SUP-003',
                'name' => 'EJ General Supplier',
                'contact_person' => 'John Reyes',
                'email' => 'john@ejgeneral.test',
                'phone' => '09191234567',
                'address' => 'Digos City, Davao del Sur',
                'is_active' => true,
                'status' => 'active',
            ],
            [
                'supplier_code' => 'SUP-004',
                'name' => 'Cotabato General Merchandise',
                'contact_person' => 'Maria Dela Cruz',
                'email' => 'maria@cotabatogeneral.test',
                'phone' => '09201234567',
                'address' => 'Cotabato City',
                'is_active' => true,
                'status' => 'active',
            ],
            [
                'supplier_code' => 'SUP-005',
                'name' => 'Southern Mindanao Distribution',
                'contact_person' => 'Carlo Mendoza',
                'email' => 'carlo@smdistribution.test',
                'phone' => '09351234567',
                'address' => 'General Santos City',
                'is_active' => true,
                'status' => 'active',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::updateOrCreate(
                ['supplier_code' => $supplier['supplier_code']],
                $supplier
            );
        }
    }
}