<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('second_hand_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('price');
            $table->string('seller_nickname', 50);
            $table->string('photo_path');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('second_hand_items');
    }
};
