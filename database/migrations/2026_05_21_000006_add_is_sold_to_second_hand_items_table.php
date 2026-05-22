<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('second_hand_items', function (Blueprint $table) {
            $table->boolean('is_sold')->default(false)->after('contact_value');
        });
    }

    public function down(): void
    {
        Schema::table('second_hand_items', function (Blueprint $table) {
            $table->dropColumn('is_sold');
        });
    }
};
