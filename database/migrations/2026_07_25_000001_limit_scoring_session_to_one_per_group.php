<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_scoring_sessions', function (Blueprint $table) {
            $table->unique('event_group_id', 'event_scoring_sessions_group_unique');
        });
    }

    public function down(): void
    {
        Schema::table('event_scoring_sessions', function (Blueprint $table) {
            $table->dropUnique('event_scoring_sessions_group_unique');
        });
    }
};
