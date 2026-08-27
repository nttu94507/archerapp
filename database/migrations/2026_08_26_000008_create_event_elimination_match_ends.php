<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->unsignedSmallInteger('participant_one_total')->default(0)->after('current_set');
            $table->unsignedSmallInteger('participant_two_total')->default(0)->after('participant_one_total');
            $table->unsignedTinyInteger('current_end')->default(1)->after('participant_two_total');
        });

        Schema::create('event_elimination_match_ends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_elimination_match_id')->constrained('event_elimination_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('end_number');
            $table->json('participant_one_arrows');
            $table->json('participant_two_arrows');
            $table->unsignedTinyInteger('participant_one_end_total');
            $table->unsignedTinyInteger('participant_two_end_total');
            $table->unsignedSmallInteger('participant_one_running_total');
            $table->unsignedSmallInteger('participant_two_running_total');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_elimination_match_id', 'end_number'], 'elimination_match_end_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_elimination_match_ends');
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->dropColumn(['participant_one_total', 'participant_two_total', 'current_end']);
        });
    }
};
