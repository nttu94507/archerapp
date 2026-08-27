<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_ranking_snapshots')) Schema::create('event_ranking_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_group_id')->constrained('event_groups')->cascadeOnDelete();
            $table->foreignId('event_phase_id')->constrained('event_phases')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 30)->default('locked');
            $table->string('source_hash', 64);
            $table->json('ranking_rule');
            $table->timestamp('locked_at');
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_phase_id', 'version'], 'event_phase_ranking_version_unique');
            $table->index(['event_phase_id', 'superseded_at']);
        });

        if (! Schema::hasTable('event_ranking_snapshot_entries')) Schema::create('event_ranking_snapshot_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_ranking_snapshot_id')->constrained('event_ranking_snapshots')->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('rank_position')->nullable();
            $table->unsignedInteger('seed_position')->nullable();
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('ten_count')->default(0);
            $table->unsignedInteger('x_count')->default(0);
            $table->string('result_status', 30)->nullable();
            $table->boolean('is_eligible')->default(true);
            $table->unsignedInteger('tie_group')->nullable();
            $table->boolean('requires_tiebreak')->default(false);
            $table->string('athlete_name');
            $table->string('team_name')->nullable();
            $table->timestamps();

            $table->unique(['event_ranking_snapshot_id', 'event_registration_id'], 'ranking_snapshot_registration_unique');
            $table->unique(['event_ranking_snapshot_id', 'seed_position'], 'ranking_snapshot_seed_unique');
            $table->index(['event_ranking_snapshot_id', 'rank_position'], 'ranking_snapshot_rank_index');
        });

        if (Schema::hasTable('event_ranking_snapshot_entries') && ! $this->hasIndex('event_ranking_snapshot_entries', 'ranking_snapshot_rank_index')) {
            Schema::table('event_ranking_snapshot_entries', function (Blueprint $table): void {
                $table->index(['event_ranking_snapshot_id', 'rank_position'], 'ranking_snapshot_rank_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_ranking_snapshot_entries');
        Schema::dropIfExists('event_ranking_snapshots');
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }
};
