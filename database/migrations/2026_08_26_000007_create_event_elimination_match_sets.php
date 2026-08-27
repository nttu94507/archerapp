<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->unsignedTinyInteger('participant_one_set_points')->default(0)->after('participant_two_seed');
            $table->unsignedTinyInteger('participant_two_set_points')->default(0)->after('participant_one_set_points');
            $table->unsignedTinyInteger('current_set')->default(1)->after('participant_two_set_points');
        });

        Schema::create('event_elimination_match_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_elimination_match_id')->constrained('event_elimination_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->json('participant_one_arrows');
            $table->json('participant_two_arrows');
            $table->unsignedTinyInteger('participant_one_total');
            $table->unsignedTinyInteger('participant_two_total');
            $table->unsignedTinyInteger('participant_one_set_points');
            $table->unsignedTinyInteger('participant_two_set_points');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_elimination_match_id', 'set_number'], 'elimination_match_set_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_elimination_match_sets');
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->dropColumn(['participant_one_set_points', 'participant_two_set_points', 'current_set']);
        });
    }
};
