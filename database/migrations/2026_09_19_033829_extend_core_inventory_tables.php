<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->decimal('reorder_level', 20, 4)->nullable();
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->index(['occurred_at', 'id'], 'movements_chronology_index');
            $table->index(['stock_item_id', 'occurred_at', 'id'], 'movements_item_history_index');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_nonnegative CHECK (quantity >= 0 AND reserved_quantity >= 0 AND reserved_quantity <= quantity AND (reorder_level IS NULL OR reorder_level >= 0))');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE inventories DROP CHECK inventories_nonnegative');
        }
        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropColumn('reorder_level');
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('movements_chronology_index');
            $table->dropIndex('movements_item_history_index');
        });
    }
};
