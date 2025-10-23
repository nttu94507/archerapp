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
        Schema::create('archery_shots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('archery_sessions')->cascadeOnDelete();

            $table->unsignedSmallInteger('end_seq');   // 第幾趟（1..ceil(total/per)）
            $table->unsignedTinyInteger('shot_seq');   // 此趟第幾箭（1..per）

            // 分數（0..11，X 以 10 計分）
            $table->unsignedTinyInteger('score');      // 0..11
            $table->boolean('is_x')->default(false);   // 顯示/統計 X
            $table->boolean('is_miss')->default(false);// Miss=M(0)

            $table->timestamps();

            // 不重覆同一場同一格
            $table->unique(['session_id','end_seq','shot_seq']);

            // 常用查詢
            $table->index(['session_id','end_seq']);
            $table->index(['is_x','is_miss']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archery_shots');
    }
};
