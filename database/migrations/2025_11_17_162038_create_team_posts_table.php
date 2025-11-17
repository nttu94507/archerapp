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
        Schema::create('team_posts', function (Blueprint $table) {
            $table->id();

            // 如果不需要綁使用者，可以把這行拿掉
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('title');      // 標題
            $table->text('content');      // 內文
            $table->string('contact');    // 聯繫方式（Line / IG / 電話等等）

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_posts');
    }
};
