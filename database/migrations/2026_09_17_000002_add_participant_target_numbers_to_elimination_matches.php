<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->string('participant_one_target_number', 20)->nullable()->after('target_number');
            $table->string('participant_two_target_number', 20)->nullable()->after('participant_one_target_number');
        });
    }

    public function down(): void
    {
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->dropColumn(['participant_one_target_number', 'participant_two_target_number']);
        });
    }
};
