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
        Schema::create('event_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // 只允許已註冊
            $t->enum('role', ['owner','manager','staff','viewer'])->default('viewer')->index();
//            $t->json('permissions')->default(json_encode([])); // 預設 []
            $t->enum('status', ['active','invited','revoked'])->default('invited')->index();
            $t->string('invitation_token', 64)->nullable()->unique();
            $t->timestamp('invitation_expires_at')->nullable();

            $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('invited_at')->nullable();
            $t->timestamp('accepted_at')->nullable();

            $t->timestamps();
            $t->softDeletes(); // 可選：保留歷史

            $t->unique(['event_id','user_id']);           // 一人一賽事一筆
            $t->index(['event_id','status']);             // 常見篩選
            $t->index(['event_id','role']);               // 角色過濾
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_staff');
    }
};
