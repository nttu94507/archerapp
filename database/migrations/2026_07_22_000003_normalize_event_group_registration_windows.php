<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_groups') || ! Schema::hasColumn('event_groups', 'reg_start') || ! Schema::hasColumn('event_groups', 'reg_end')) return;

        DB::table('event_groups')
            ->where(fn ($query) => $query->whereNull('reg_start')->whereNotNull('reg_end'))
            ->orWhere(fn ($query) => $query->whereNotNull('reg_start')->whereNull('reg_end'))
            ->orderBy('id')
            ->each(function (object $group): void {
                $event = DB::table('events')->where('id', $group->event_id)->first(['reg_start', 'reg_end']);
                $start = $group->reg_start ?? $event?->reg_start;
                $end = $group->reg_end ?? $event?->reg_end;
                DB::table('event_groups')->where('id', $group->id)->update([
                    'reg_start' => $start && $end ? $start : null,
                    'reg_end' => $start && $end ? $end : null,
                ]);
            });
    }

    public function down(): void {}
};
