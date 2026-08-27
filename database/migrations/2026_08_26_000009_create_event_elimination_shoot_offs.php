<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_elimination_shoot_offs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_elimination_match_id')->constrained('event_elimination_matches')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number');
            $table->string('participant_one_arrow', 2);
            $table->string('participant_two_arrow', 2);
            $table->unsignedTinyInteger('participant_one_value');
            $table->unsignedTinyInteger('participant_two_value');
            $table->string('status', 30);
            $table->string('decision_type', 30)->nullable();
            $table->foreignId('winner_registration_id')->nullable()->constrained('event_registrations')->restrictOnDelete();
            $table->text('decision_note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('judged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('judged_at')->nullable();
            $table->timestamps();

            $table->unique(['event_elimination_match_id', 'attempt_number'], 'elimination_shoot_off_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_elimination_shoot_offs');
    }
};
