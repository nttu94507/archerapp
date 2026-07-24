<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_scoring_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_group_id')->constrained('event_groups')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('total_arrows');
            $table->unsignedTinyInteger('arrows_per_end')->default(6);
            $table->unsignedTinyInteger('athletes_per_target')->default(4);
            $table->string('status', 30)->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('event_scoring_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_scoring_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('target_number');
            $table->uuid('access_token')->unique();
            $table->string('status', 30)->default('ready');
            $table->unsignedSmallInteger('last_completed_end')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['event_scoring_session_id', 'target_number'], 'scoring_session_target_unique');
        });

        Schema::create('event_scoring_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_scoring_target_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->char('position', 1);
            $table->timestamps();
            $table->unique(['event_scoring_target_id', 'position'], 'scoring_target_position_unique');
            $table->unique(['event_scoring_target_id', 'event_registration_id'], 'scoring_target_registration_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_scoring_assignments');
        Schema::dropIfExists('event_scoring_targets');
        Schema::dropIfExists('event_scoring_sessions');
    }
};
