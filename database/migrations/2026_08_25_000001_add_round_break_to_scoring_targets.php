<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_scoring_targets', function (Blueprint $table): void {
            $table->timestamp('first_round_completed_at')->nullable()->after('last_completed_end');
            $table->timestamp('second_round_started_at')->nullable()->after('first_round_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_scoring_targets', function (Blueprint $table): void {
            $table->dropColumn(['first_round_completed_at', 'second_round_started_at']);
        });
    }
};
