<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_groups', function (Blueprint $table): void {
            $table->boolean('live_results_visible')->default(false)->after('reg_end')->index();
        });
    }

    public function down(): void
    {
        Schema::table('event_groups', function (Blueprint $table): void {
            $table->dropIndex(['live_results_visible']);
            $table->dropColumn('live_results_visible');
        });
    }
};
