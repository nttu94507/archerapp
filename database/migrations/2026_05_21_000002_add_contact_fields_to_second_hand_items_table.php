<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('second_hand_items', function (Blueprint $table) {
            $table->string('contact_type', 20)->default('phone')->after('description');
            $table->string('contact_value', 100)->after('contact_type');
        });
    }

    public function down(): void
    {
        Schema::table('second_hand_items', function (Blueprint $table) {
            $table->dropColumn(['contact_type', 'contact_value']);
        });
    }
};
