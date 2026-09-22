<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table): void {
            $table->renameColumn('expires_at', 'expiration_date');
            $table->index(['warehouse_id', 'expiration_date'], 'batches_warehouse_expiration_index');
        });
        Schema::table('purchase_receipt_items', function (Blueprint $table): void {
            $table->string('batch_number')->nullable();
            $table->date('expiration_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipt_items', function (Blueprint $table): void {
            $table->dropColumn(['batch_number', 'expiration_date']);
        });
        Schema::table('product_batches', function (Blueprint $table): void {
            $table->dropIndex('batches_warehouse_expiration_index');
            $table->renameColumn('expiration_date', 'expires_at');
        });
    }
};
