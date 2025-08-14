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
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('distance'); // m
            $table->unsignedSmallInteger('target_face'); // cm
            $table->unsignedSmallInteger('arrow_count');
            $table->unsignedSmallInteger('max_score'); // 該 round 滿分
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
