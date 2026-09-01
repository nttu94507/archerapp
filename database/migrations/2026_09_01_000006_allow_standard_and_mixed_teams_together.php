<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_groups', function (Blueprint $table): void {
            $table->boolean('standard_team_enabled')->default(false)->after('is_team');
            $table->boolean('mixed_team_enabled')->default(false)->after('standard_team_enabled');
        });
        Schema::table('event_teams', function (Blueprint $table): void {
            $table->string('team_format', 20)->default('standard')->after('name')->index();
        });

        DB::table('event_groups')->where('is_team', true)->where('team_type', 'standard')->update(['standard_team_enabled'=>true]);
        DB::table('event_groups')->where('is_team', true)->where('team_type', 'mixed')->update(['mixed_team_enabled'=>true]);
        DB::table('event_teams')->orderBy('id')->each(function ($team): void {
            $format = DB::table('event_groups')->where('id', $team->event_group_id)->value('team_type') ?: 'standard';
            DB::table('event_teams')->where('id', $team->id)->update(['team_format'=>$format]);
        });
    }

    public function down(): void
    {
        Schema::table('event_teams', fn (Blueprint $table) => $table->dropColumn('team_format'));
        Schema::table('event_groups', fn (Blueprint $table) => $table->dropColumn(['standard_team_enabled','mixed_team_enabled']));
    }
};
