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
        Schema::create('event_registrations', function (Blueprint $t) {
            $t->id();

            // 關聯
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignId('event_group_id')->constrained('event_groups')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // 參賽者資料
            $t->string('name');
            $t->string('email');
            $t->string('phone')->nullable();
            $t->string('team_name')->nullable(); // 隊際用

            // 狀態（照你提供的列舉）
            $t->enum('status', ['registered','checked_in','withdrawn','refunded','no_show'])
                ->default('registered')->index();

            // 退賽／退款資訊
            $t->text('withdraw_reason')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();

            // 付款
            $t->boolean('paid')->default(false);

            $t->timestamps();

            // 防重複：同一活動+同一組別+同一 Email 只能有一筆有效報名（不論狀態）
            $t->unique(['event_id', 'event_group_id', 'email']);

            // 常用索引
            $t->index(['event_id','event_group_id','status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
