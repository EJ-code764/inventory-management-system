<?php

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->date('expected_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            foreach (['subtotal', 'discount', 'tax', 'total'] as $field) {
                $table->decimal($field, 20, 4)->default(0);
            }
            $table->index('ordered_at');
        });
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            foreach (['discount', 'tax', 'subtotal'] as $field) {
                $table->decimal($field, 20, 4)->default(0);
            }
        });
        Schema::table('purchase_receipts', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->string('request_hash', 64)->nullable();
        });
        DB::table('purchase_orders')->orderBy('id')->chunkById(100, function (Collection $orders): void {
            foreach ($orders as $order) {
                $total = BigDecimal::of('0.0000');
                foreach (DB::table('purchase_order_items')->where('purchase_order_id', $order->id)->get() as $item) {
                    $subtotal = BigDecimal::of((string) $item->ordered_quantity)->multipliedBy((string) $item->unit_cost)->toScale(4, RoundingMode::HalfUp);
                    DB::table('purchase_order_items')->where('id', $item->id)->update(['subtotal' => (string) $subtotal]);
                    $total = $total->plus($subtotal);
                }
                DB::table('purchase_orders')->where('id', $order->id)->update(['subtotal' => (string) $total, 'total' => (string) $total]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipts', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'request_hash']);
        });
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropColumn(['discount', 'tax', 'subtotal']);
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex(['ordered_at']);
            $table->dropColumn(['expected_at', 'revision', 'subtotal', 'discount', 'tax', 'total']);
        });
    }
};
