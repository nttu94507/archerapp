<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_elimination_brackets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_group_id')->constrained('event_groups')->cascadeOnDelete();
            $table->foreignId('event_phase_id')->constrained('event_phases')->cascadeOnDelete();
            $table->foreignId('event_ranking_snapshot_id')->constrained('event_ranking_snapshots')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('category', 30)->default('individual');
            $table->string('scoring_mode', 30);
            $table->unsignedSmallInteger('bracket_size');
            $table->string('status', 30)->default('ready');
            $table->boolean('bronze_match_enabled')->default(true);
            $table->timestamp('locked_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('event_phase_id');
            $table->unique('event_ranking_snapshot_id', 'elimination_snapshot_unique');
            $table->index(['event_id', 'event_group_id', 'status']);
        });

        Schema::create('event_elimination_matches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_elimination_bracket_id')->constrained('event_elimination_brackets')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_number');
            $table->unsignedSmallInteger('position');
            $table->string('match_type', 20)->default('main');
            $table->string('label', 60);
            $table->string('status', 30)->default('pending');
            $table->foreignId('participant_one_snapshot_entry_id')->nullable();
            $table->foreignId('participant_two_snapshot_entry_id')->nullable();
            $table->foreignId('participant_one_registration_id')->nullable();
            $table->foreignId('participant_two_registration_id')->nullable();
            $table->unsignedSmallInteger('participant_one_seed')->nullable();
            $table->unsignedSmallInteger('participant_two_seed')->nullable();
            $table->unsignedBigInteger('next_match_id')->nullable();
            $table->unsignedTinyInteger('next_slot')->nullable();
            $table->unsignedBigInteger('loser_next_match_id')->nullable();
            $table->unsignedTinyInteger('loser_next_slot')->nullable();
            $table->foreignId('winner_registration_id')->nullable()->constrained('event_registrations')->restrictOnDelete();
            $table->foreignId('loser_registration_id')->nullable()->constrained('event_registrations')->restrictOnDelete();
            $table->string('target_number', 20)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('participant_one_snapshot_entry_id', 'elim_match_p1_entry_fk')->references('id')->on('event_ranking_snapshot_entries')->restrictOnDelete();
            $table->foreign('participant_two_snapshot_entry_id', 'elim_match_p2_entry_fk')->references('id')->on('event_ranking_snapshot_entries')->restrictOnDelete();
            $table->foreign('participant_one_registration_id', 'elim_match_p1_registration_fk')->references('id')->on('event_registrations')->restrictOnDelete();
            $table->foreign('participant_two_registration_id', 'elim_match_p2_registration_fk')->references('id')->on('event_registrations')->restrictOnDelete();

            $table->unique(['event_elimination_bracket_id', 'match_type', 'round_number', 'position'], 'elimination_match_position_unique');
            $table->index(['event_elimination_bracket_id', 'round_number', 'position'], 'elimination_round_position_index');
        });

        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->foreign('next_match_id')->references('id')->on('event_elimination_matches')->nullOnDelete();
            $table->foreign('loser_next_match_id')->references('id')->on('event_elimination_matches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_elimination_matches');
        Schema::dropIfExists('event_elimination_brackets');
    }
};
