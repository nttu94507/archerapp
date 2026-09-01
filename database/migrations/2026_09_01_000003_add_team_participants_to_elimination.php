<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_elimination_brackets', function (Blueprint $table): void {
            $table->dropUnique('elimination_snapshot_unique');
            $table->unique(['event_ranking_snapshot_id','category'], 'elim_snapshot_category_uq');
        });
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->foreignId('participant_one_team_id')->nullable()->after('participant_two_registration_id')->constrained('event_teams')->restrictOnDelete();
            $table->foreignId('participant_two_team_id')->nullable()->after('participant_one_team_id')->constrained('event_teams')->restrictOnDelete();
            $table->foreignId('winner_team_id')->nullable()->after('loser_registration_id')->constrained('event_teams')->restrictOnDelete();
            $table->foreignId('loser_team_id')->nullable()->after('winner_team_id')->constrained('event_teams')->restrictOnDelete();
        });
        Schema::table('event_elimination_shoot_offs', function (Blueprint $table): void {
            $table->json('participant_one_arrows')->nullable();
            $table->json('participant_two_arrows')->nullable();
            $table->foreignId('winner_team_id')->nullable()->constrained('event_teams')->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('event_elimination_shoot_offs', function (Blueprint $table): void { $table->dropConstrainedForeignId('winner_team_id'); $table->dropColumn(['participant_one_arrows','participant_two_arrows']); });
        Schema::table('event_elimination_matches', function (Blueprint $table): void { foreach(['participant_one_team_id','participant_two_team_id','winner_team_id','loser_team_id'] as $column) $table->dropConstrainedForeignId($column); });
        Schema::table('event_elimination_brackets', function (Blueprint $table): void { $table->dropUnique('elim_snapshot_category_uq'); $table->unique('event_ranking_snapshot_id','elimination_snapshot_unique'); });
    }
};
