<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_badges') && ! Schema::hasColumn('event_badges', 'icon_path')) {
            Schema::table('event_badges', function (Blueprint $table): void {
                $table->string('icon_path')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_badges') && Schema::hasColumn('event_badges', 'icon_path')) {
            Schema::table('event_badges', fn (Blueprint $table) => $table->dropColumn('icon_path'));
        }
    }
};
