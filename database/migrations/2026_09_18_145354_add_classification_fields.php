<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'brands', 'units'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->string('status', 16)->default('active')->index();
                if ($name === 'units') {
                    $table->string('short_name')->nullable();
                } else {
                    $table->text('description')->nullable();
                }
            });
            DB::table($name)->where('is_active', false)->update(['status' => 'inactive']);
        }
        DB::table('units')->update(['short_name' => DB::raw('code')]);
    }

    public function down(): void
    {
        foreach (['categories', 'brands', 'units'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->dropIndex(['status']);
                $table->dropColumn(['status', $name === 'units' ? 'short_name' : 'description']);
            });
        }
    }
};
