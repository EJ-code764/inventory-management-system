<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'activity_chronology_index');
            $table->index(['causer_id', 'created_at', 'id'], 'activity_user_date_index');
            $table->index(['event', 'created_at', 'id'], 'activity_event_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex('activity_chronology_index');
            $table->dropIndex('activity_user_date_index');
            $table->dropIndex('activity_event_date_index');
        });
    }
};
