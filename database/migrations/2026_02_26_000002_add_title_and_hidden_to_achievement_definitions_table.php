<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achievement_definitions', function (Blueprint $table) {
            $table->string('title_name')->nullable()->after('description');
            $table->boolean('is_hidden')->default(false)->after('is_active');
            $table->index(['is_active', 'is_hidden', 'category'], 'achievement_active_hidden_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('achievement_definitions', function (Blueprint $table) {
            $table->dropIndex('achievement_active_hidden_category_idx');
            $table->dropColumn(['title_name', 'is_hidden']);
        });
    }
};
