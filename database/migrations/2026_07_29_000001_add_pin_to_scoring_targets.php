<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_scoring_targets', function (Blueprint $table) {
            $table->string('device_pin', 6)->nullable()->after('access_token');
        });

        DB::table('event_scoring_targets')
            ->whereNull('device_pin')
            ->orderBy('id')
            ->eachById(function ($target): void {
                DB::table('event_scoring_targets')
                    ->where('id', $target->id)
                    ->update(['device_pin'=>(string) random_int(100000, 999999)]);
            });
    }

    public function down(): void
    {
        Schema::table('event_scoring_targets', function (Blueprint $table) {
            $table->dropColumn('device_pin');
        });
    }
};
