<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_achievement_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_definition_id')->constrained('achievement_definitions')->cascadeOnDelete();
            $table->unsignedInteger('current_value')->default(0);
            $table->unsignedInteger('target_value');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'achievement_definition_id'], 'user_achievement_unique');
            $table->index(['user_id', 'unlocked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievement_progress');
    }
};
