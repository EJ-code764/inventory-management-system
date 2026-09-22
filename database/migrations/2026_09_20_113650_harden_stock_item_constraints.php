<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items ADD CONSTRAINT stock_items_exactly_one_owner CHECK ((product_id IS NULL) <> (product_variant_id IS NULL)), ADD CONSTRAINT stock_items_nonnegative CHECK (cost_price >= 0 AND selling_price >= 0 AND reorder_level >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items DROP CHECK stock_items_exactly_one_owner, DROP CHECK stock_items_nonnegative');
        }
    }
};
