<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('type', 30)->default('participant');
            $table->string('eligibility', 30)->default('registered');
            $table->uuid('claim_token')->unique();
            $table->boolean('claim_enabled')->default(false);
            $table->timestamp('claim_starts_at')->nullable();
            $table->timestamp('claim_ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'is_active']);
        });

        Schema::create('event_badge_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->boolean('is_eligible')->default(false);
            $table->string('eligibility_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['event_badge_id', 'user_id']);
            $table->index(['event_badge_id', 'status']);
        });

        Schema::create('user_event_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_badge_claim_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('awarded_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->timestamps();

            $table->unique(['event_badge_id', 'user_id']);
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_event_badges');
        Schema::dropIfExists('event_badge_claims');
        Schema::dropIfExists('event_badges');
    }
};
