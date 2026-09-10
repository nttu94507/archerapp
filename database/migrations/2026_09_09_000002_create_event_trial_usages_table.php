<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_trial_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('consumed_at');
            $table->timestamps();
            $table->index(['user_id', 'consumed_at'], 'trial_user_consumed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_trial_usages');
    }
};
