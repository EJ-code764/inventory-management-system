<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->string('status')->default('active')->index();
        });
        DB::table('warehouses')->where('is_active', false)->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        DB::table('warehouses')->where('status', 'active')->update(['is_active' => true]);
        DB::table('warehouses')->where('status', 'inactive')->update(['is_active' => false]);
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
