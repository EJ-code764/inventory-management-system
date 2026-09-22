<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::where('code', 'MAIN')->firstOrFail();

        $products = [
            [
                'name' => 'Coca-Cola 1.5L',
                'category' => 'Beverages',
                'brand' => 'Coca-Cola',
                'unit' => 'BTL',
                'sku' => 'BEV-001',
                'barcode' => '480001000001',
                'cost_price' => 55,
                'selling_price' => 70,
                'quantity' => 24,
                'reorder_level' => 10,
            ],
            [
                'name' => 'Sprite 1.5L',
                'category' => 'Beverages',
                'brand' => 'Sprite',
                'unit' => 'BTL',
                'sku' => 'BEV-002',
                'barcode' => '480001000002',
                'cost_price' => 54,
                'selling_price' => 68,
                'quantity' => 8,
                'reorder_level' => 10,
            ],
            [
                'name' => 'Bottled Water 500ml',
                'category' => 'Beverages',
                'brand' => 'Generic',
                'unit' => 'BTL',
                'sku' => 'BEV-003',
                'barcode' => '480001000003',
                'cost_price' => 10,
                'selling_price' => 15,
                'quantity' => 50,
                'reorder_level' => 20,
            ],
            [
                'name' => 'Piattos Cheese 85g',
                'category' => 'Snacks',
                'brand' => 'Jack n Jill',
                'unit' => 'PCS',
                'sku' => 'SNK-001',
                'barcode' => '480001000004',
                'cost_price' => 30,
                'selling_price' => 38,
                'quantity' => 20,
                'reorder_level' => 10,
            ],
            [
                'name' => 'Nova Country Cheddar',
                'category' => 'Snacks',
                'brand' => 'Jack n Jill',
                'unit' => 'PCS',
                'sku' => 'SNK-002',
                'barcode' => '480001000005',
                'cost_price' => 29,
                'selling_price' => 37,
                'quantity' => 6,
                'reorder_level' => 10,
            ],
            [
                'name' => 'Century Tuna 180g',
                'category' => 'Canned Goods',
                'brand' => 'Century',
                'unit' => 'PCS',
                'sku' => 'CAN-001',
                'barcode' => '480001000006',
                'cost_price' => 35,
                'selling_price' => 45,
                'quantity' => 15,
                'reorder_level' => 8,
            ],
            [
                'name' => 'Argentina Corned Beef',
                'category' => 'Canned Goods',
                'brand' => 'Argentina',
                'unit' => 'PCS',
                'sku' => 'CAN-002',
                'barcode' => '480001000007',
                'cost_price' => 32,
                'selling_price' => 42,
                'quantity' => 12,
                'reorder_level' => 8,
            ],
            [
                'name' => 'Safeguard Soap',
                'category' => 'Personal Care',
                'brand' => 'Safeguard',
                'unit' => 'PCS',
                'sku' => 'PER-001',
                'barcode' => '480001000008',
                'cost_price' => 32,
                'selling_price' => 42,
                'quantity' => 25,
                'reorder_level' => 10,
            ],
            [
                'name' => 'Tide Detergent 70g',
                'category' => 'Household',
                'brand' => 'Tide',
                'unit' => 'PCS',
                'sku' => 'HOU-001',
                'barcode' => '480001000009',
                'cost_price' => 10,
                'selling_price' => 13,
                'quantity' => 40,
                'reorder_level' => 15,
            ],
            [
                'name' => 'HBW Ballpen Black',
                'category' => 'School Supplies',
                'brand' => 'HBW',
                'unit' => 'PCS',
                'sku' => 'SCH-001',
                'barcode' => '480001000010',
                'cost_price' => 5,
                'selling_price' => 10,
                'quantity' => 60,
                'reorder_level' => 20,
            ],
        ];

        foreach ($products as $data) {
            $category = Category::where('name', $data['category'])->firstOrFail();
            $brand = Brand::where('name', $data['brand'])->firstOrFail();
            $unit = Unit::where('code', $data['unit'])->firstOrFail();

            // Product = general product information
            $product = Product::updateOrCreate(
                ['name' => $data['name']],
                [
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'unit_id' => $unit->id,
                    'description' => 'Sample inventory product',
                    'status' => 'active',
                    'has_variants' => false,
                ]
            );

            // StockItem = SKU, barcode and pricing
            $stockItem = StockItem::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'product_id' => $product->id,
                    'product_variant_id' => null,
                    'barcode' => $data['barcode'],
                    'cost_price' => $data['cost_price'],
                    'selling_price' => $data['selling_price'],
                    'reorder_level' => $data['reorder_level'],
                    'is_active' => true,
                ]
            );

            // Inventory = actual stock at a warehouse
            Inventory::updateOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'stock_item_id' => $stockItem->id,
                ],
                [
                    'quantity' => $data['quantity'],
                    'reserved_quantity' => 0,
                    'reorder_level' => $data['reorder_level'],
                ]
            );
        }
    }
}