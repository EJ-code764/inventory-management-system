<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->renameColumn('requested_by', 'created_by');
            $table->renameColumn('notes', 'remarks');
            $table->date('transfer_date')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->index(['source_warehouse_id', 'status']);
            $table->index(['destination_warehouse_id', 'status']);
        });
        DB::table('stock_transfers')->whereNotNull('created_at')->update(['transfer_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropIndex(['transfer_date']);
            $table->dropIndex(['source_warehouse_id', 'status']);
            $table->dropIndex(['destination_warehouse_id', 'status']);
            $table->dropColumn(['transfer_date', 'completed_at', 'revision']);
            $table->renameColumn('created_by', 'requested_by');
            $table->renameColumn('remarks', 'notes');
        });
    }
};
