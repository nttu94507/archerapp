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
        Schema::create('archery_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 基本設定
            $table->enum('bow_type', ['recurve','compound','barebow','yumi','longbow']);
            $table->enum('venue', ['indoor','outdoor']);
            $table->unsignedSmallInteger('distance_m');        // 5..150
            $table->unsignedSmallInteger('arrows_total');      // 1..300
            $table->unsignedTinyInteger('arrows_per_end');     // 1..12

            // 彙總快取（查列表省重算）
            $table->unsignedInteger('score_total')->default(0);
            $table->unsignedInteger('x_count')->default(0);
            $table->unsignedInteger('m_count')->default(0);

            // 備註（可選）
            $table->string('note', 255)->nullable();

            $table->timestamps();

            // 索引
            $table->index(['user_id','created_at']);
            $table->index(['bow_type','venue','distance_m']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archery_sessions');
    }
};
