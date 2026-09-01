<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_teams', function (Blueprint $table): void {
            $table->boolean('is_open')->default(true)->after('name')->index();
            $table->string('recruitment_note', 300)->nullable()->after('is_open');
        });
    }

    public function down(): void
    {
        Schema::table('event_teams', function (Blueprint $table): void {
            $table->dropColumn(['is_open', 'recruitment_note']);
        });
    }
};
