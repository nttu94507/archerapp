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
        Schema::table('archery_sessions', function (Blueprint $table) {
            $table->string('target_face', 20)->default('ten-ring')->after('arrows_per_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archery_sessions', function (Blueprint $table) {
            $table->dropColumn('target_face');
        });
    }
};
