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
            $table->date('start_date')->index();                   // 起始日
            $table->date('end_date')->index();                     // 結束日（單日 = 同起日）
            $table->enum('mode', ['indoor', 'outdoor'])->index();  // 室內/室外
            $table->boolean('verified')->default(true)->index();   // 是否驗證
            $table->string('level')->nullable();                   // local/regional/national...
            $table->string('organizer');                           // 主辦單位

            $table->dateTime('reg_start')->nullable();             // 報名開始
            $table->dateTime('reg_end')->nullable();               // 報名截止

            $table->string('venue')->nullable();                   // 場地名稱
            $table->string('map_link')->nullable();                // Google 地圖連結
            $table->decimal('lat', 10, 7)->nullable();             // 緯度
            $table->decimal('lng', 10, 7)->nullable();             // 經度

            $table->enum('status', ['draft','pending','approved','rejected','archived'])
                ->default('pending')->index();
            $table->timestamp('published_at')->nullable();

            // 若你想追蹤建立者，可取消註解
            // $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // 常用複合索引（查即將舉辦）
            $table->index(['start_date','end_date']);
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
