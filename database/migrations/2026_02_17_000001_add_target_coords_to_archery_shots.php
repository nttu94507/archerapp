<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archery_shots', function (Blueprint $table) {
            $table->decimal('target_x', 6, 3)->nullable()->after('is_miss');
            $table->decimal('target_y', 6, 3)->nullable()->after('target_x');
            $table->index(['target_x', 'target_y'], 'archery_shots_target_xy_index');
        });
    }

    public function down(): void
    {
        Schema::table('archery_shots', function (Blueprint $table) {
            $table->dropIndex('archery_shots_target_xy_index');
            $table->dropColumn(['target_x', 'target_y']);
        });
    }
};
