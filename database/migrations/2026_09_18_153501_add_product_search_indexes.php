<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['name', 'id'], 'products_name_id_index');
            $table->index(['brand_id', 'status'], 'products_brand_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_name_id_index');
            $table->dropIndex('products_brand_status_index');
        });
    }
};
