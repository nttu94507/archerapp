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
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('profile_completed_at')->nullable()->after('remember_token');
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // 基本聯絡
            $table->string('phone')->nullable()->index();
            $table->string('city')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // 競賽相關（依你的情境可擴充/縮減）
            $table->date('birthdate')->nullable()->index();              // 年齡組別判斷
            $table->enum('handedness', ['left','right','both'])->nullable();
            $table->enum('bow_type', ['recurve','compound','barebow','traditional'])->nullable();
            $table->string('club_name')->nullable();

            // 法務
            $table->timestamp('consent_signed_at')->nullable();           // 通用切結/隱私條款
            $table->string('consent_version')->nullable();               // 便於未來版本升級時重新簽署

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_completed_at');
        });
    }
};
