<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->renameColumn('contact_name', 'contact_person');
            $table->string('supplier_code', 64)->nullable();
            $table->string('status')->default('active')->index();
        });
        DB::table('suppliers')->orderBy('id')->chunkById(200, function (Collection $suppliers): void {
            foreach ($suppliers as $supplier) {
                DB::table('suppliers')->where('id', $supplier->id)->update([
                    'supplier_code' => 'SUP-'.str_pad((string) $supplier->id, 6, '0', STR_PAD_LEFT),
                    'status' => $supplier->is_active ? 'active' : 'inactive',
                ]);
            }
        });
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('supplier_code', 64)->nullable(false)->change();
            $table->unique('supplier_code');
        });
    }

    public function down(): void
    {
        DB::table('suppliers')->where('status', 'active')->update(['is_active' => true]);
        DB::table('suppliers')->where('status', 'inactive')->update(['is_active' => false]);
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique(['supplier_code']);
            $table->dropIndex(['status']);
            $table->dropColumn(['supplier_code', 'status']);
            $table->renameColumn('contact_person', 'contact_name');
        });
    }
};
