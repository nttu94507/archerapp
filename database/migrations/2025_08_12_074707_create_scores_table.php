<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('total_score');
            $table->unsignedSmallInteger('x_count')->default(0);
            $table->unsignedSmallInteger('ten_count')->default(0);
            $table->unsignedSmallInteger('arrow_count');
            $table->decimal('stdev', 6, 3)->default(0); // 單場每箭分數標準差（可先隨機）
            $table->timestamp('scored_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scores');
    }
};
