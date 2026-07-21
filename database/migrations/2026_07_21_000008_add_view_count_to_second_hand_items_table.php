<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('second_hand_items', function (Blueprint $table) {
            $table->unsignedBigInteger('view_count')->default(0)->after('is_sold');
        });
    }

    public function down(): void
    {
        Schema::table('second_hand_items', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });
    }
};
