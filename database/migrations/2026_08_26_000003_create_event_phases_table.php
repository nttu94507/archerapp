<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_phases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_group_id')->constrained('event_groups')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 30)->default('qualification');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('scoring_mode', 30)->default('cumulative');
            $table->string('status', 30)->default('draft');
            $table->unsignedSmallInteger('total_arrows')->nullable();
            $table->unsignedTinyInteger('arrows_per_end')->nullable();
            $table->unsignedTinyInteger('max_sets')->nullable();
            $table->unsignedTinyInteger('set_points_to_win')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['event_group_id', 'type', 'sequence'], 'event_group_phase_unique');
            $table->index(['event_id', 'type', 'status']);
        });

        Schema::table('event_scoring_sessions', function (Blueprint $table): void {
            $table->foreignId('event_phase_id')->nullable()->after('event_group_id')
                ->constrained('event_phases')->nullOnDelete();
        });

        $now = now();
        foreach (DB::table('event_groups')->orderBy('id')->get() as $group) {
            $sessions = DB::table('event_scoring_sessions')
                ->where('event_group_id', $group->id)
                ->get();
            $status = 'draft';
            $startedAt = null;
            $completedAt = null;
            if ($sessions->isNotEmpty()) {
                $status = $sessions->every(fn ($session) => $session->status === 'completed')
                    ? 'completed'
                    : ($sessions->contains(fn ($session) => in_array($session->status, ['scoring', 'completed'], true)) ? 'in_progress' : 'ready');
                $startedAt = $sessions->pluck('started_at')->filter()->sort()->first();
                $completedAt = $status === 'completed' ? $sessions->pluck('completed_at')->filter()->sort()->last() : null;
            }

            $phaseId = DB::table('event_phases')->insertGetId([
                'uuid'=>(string) Str::uuid(),
                'event_id'=>$group->event_id,
                'event_group_id'=>$group->id,
                'name'=>Str::limit($group->name.' 排名賽', 120, ''),
                'type'=>'qualification',
                'sequence'=>1,
                'scoring_mode'=>'cumulative',
                'status'=>$status,
                'total_arrows'=>$group->arrow_count ?? null,
                'arrows_per_end'=>$group->arrows_per_end ?? 6,
                'started_at'=>$startedAt,
                'completed_at'=>$completedAt,
                'created_at'=>$group->created_at ?? $now,
                'updated_at'=>$now,
            ]);

            DB::table('event_scoring_sessions')
                ->where('event_group_id', $group->id)
                ->update(['event_phase_id'=>$phaseId]);
        }
    }

    public function down(): void
    {
        Schema::table('event_scoring_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_phase_id');
        });
        Schema::dropIfExists('event_phases');
    }
};
