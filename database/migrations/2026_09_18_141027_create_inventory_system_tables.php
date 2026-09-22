<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active')->index();
            $table->boolean('has_variants')->default(false);
            $table->timestamps();
            $table->index(['category_id', 'status']);
        });
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->decimal('cost_price', 20, 4)->default(0);
            $table->decimal('selling_price', 20, 4)->default(0);
            $table->decimal('reorder_level', 20, 4)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique('product_id');
            $table->unique('product_variant_id');
        });
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('reserved_quantity', 20, 4)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'stock_item_id']);
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->date('ordered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('ordered_quantity', 20, 4);
            $table->decimal('received_quantity', 20, 4)->default(0);
            $table->decimal('unit_cost', 20, 4);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'stock_item_id']);
        });
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 4);
            $table->timestamps();
        });
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number');
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('unit_cost', 20, 4);
            $table->date('expires_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['stock_item_id', 'warehouse_id', 'batch_number']);
        });
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->timestamps();
            $table->unique(['stock_transfer_id', 'stock_item_id']);
        });
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('previous_quantity', 20, 4);
            $table->decimal('new_quantity', 20, 4);
            $table->decimal('difference', 20, 4);
            $table->string('reason');
            $table->timestamps();
            $table->unique(['stock_adjustment_id', 'stock_item_id']);
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->decimal('quantity_delta', 20, 4);
            $table->decimal('quantity_before', 20, 4);
            $table->decimal('quantity_after', 20, 4);
            $table->decimal('unit_cost', 20, 4)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
            $table->index(['warehouse_id', 'stock_item_id', 'occurred_at']);
        });
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('event')->index();
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'inventory_movements', 'stock_adjustment_items', 'stock_adjustments', 'stock_transfer_items', 'stock_transfers', 'product_batches', 'purchase_receipt_items', 'purchase_receipts', 'purchase_order_items', 'purchase_orders', 'suppliers', 'inventories', 'warehouses', 'stock_items', 'product_variants', 'products', 'units', 'brands', 'categories', 'role_user', 'permission_role', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
