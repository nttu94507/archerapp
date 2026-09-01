<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_groups', function (Blueprint $table): void {
            $table->string('team_type', 20)->default('standard')->after('team_size');
            $table->unsignedTinyInteger('team_substitute_limit')->default(0)->after('team_type');
        });
        Schema::table('event_registrations', fn (Blueprint $table) => $table->string('athlete_gender', 10)->nullable()->after('team_name'));
    }
    public function down(): void
    {
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn('athlete_gender'));
        Schema::table('event_groups', fn (Blueprint $table) => $table->dropColumn(['team_type','team_substitute_limit']));
    }
};
