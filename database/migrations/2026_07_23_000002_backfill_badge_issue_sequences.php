<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_event_badges', 'limited_serial')) return;

        DB::table('event_badges')->orderBy('id')->eachById(function (object $badge): void {
            $sequence = 1;
            DB::table('user_event_badges')->where('event_badge_id', $badge->id)
                ->orderBy('awarded_at')->orderBy('id')->get(['id'])
                ->each(function (object $award) use (&$sequence): void {
                    DB::table('user_event_badges')->where('id', $award->id)->update(['limited_serial' => $sequence++]);
                });
        });
    }

    public function down(): void {}
};
