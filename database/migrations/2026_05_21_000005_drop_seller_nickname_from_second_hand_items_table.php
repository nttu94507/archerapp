<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('second_hand_items', function (Blueprint $table) {
            $table->dropColumn('seller_nickname');
        });
    }

    public function down(): void
    {
        Schema::table('second_hand_items', function (Blueprint $table) {
            $table->string('seller_nickname', 50)->nullable()->after('seller_id');
        });
    }
};
