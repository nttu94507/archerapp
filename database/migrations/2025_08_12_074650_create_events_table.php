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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date')->index();
            $table->enum('mode', ['indoor', 'outdoor'])->index();
            $table->boolean('verified')->default(true)->index();
            $table->string('level')->nullable();         // local / regional / national...
            $table->string('organizer');                 // 主辦單位

            $table->dateTime('reg_start')->nullable();   // 開始報名時間
            $table->dateTime('reg_end')->nullable();     // 截止報名時間

            $table->string('venue')->nullable();         // 場地名稱
            $table->string('map_link')->nullable();      // Google 地圖連結
            $table->decimal('lat', 10, 7)->nullable();   // 緯度
            $table->decimal('lng', 10, 7)->nullable();   // 經度

            $table->enum('status', ['draft','pending','approved','rejected','archived'])->default('pending')->index();
//            $table->foreignId('submitted_by')->constrained('users');
//            $table->foreignId('reviewed_by')->nullable()->constrained('users');
//            $table->timestamp('reviewed_at')->nullable();
//            $table->text('reject_reason')->nullable();
            $table->timestamp('published_at')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
