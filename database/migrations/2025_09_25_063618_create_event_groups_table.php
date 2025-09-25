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
        Schema::create('event_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            $table->string('name');                 // e.g. 男子反曲弓 70m
            $table->enum('bow_type', ['recurve','compound','barebow'])->nullable(); //弓種
            $table->enum('gender', ['male','female','open'])->default('open');// 性別
            $table->string('age_class')->nullable(); // U12/U18/Master/OPEN...
            $table->string('distance')->nullable();  // 70m / 50m / 30m...
            $table->unsignedInteger('quota')->nullable(); // 名額上限
            $table->unsignedInteger('fee')->nullable();   // 報名費(元)
            $table->boolean('is_team')->default(false);//是否有團體賽


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_groups');
    }
};
