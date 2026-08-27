<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->uuid('access_token')->nullable()->unique()->after('uuid');
            $table->char('device_pin', 6)->nullable()->after('access_token');
            $table->string('device_token_hash', 64)->nullable()->after('device_pin');
            $table->timestamp('device_bound_at')->nullable()->after('device_token_hash');
            $table->timestamp('device_last_seen_at')->nullable()->after('device_bound_at');
            $table->string('device_user_agent', 500)->nullable()->after('device_last_seen_at');
        });

        foreach (DB::table('event_elimination_matches')->select('id')->get() as $match) {
            DB::table('event_elimination_matches')->where('id', $match->id)->update([
                'access_token'=>(string) Str::uuid(),
                'device_pin'=>(string) random_int(100000, 999999),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('event_elimination_matches', function (Blueprint $table): void {
            $table->dropUnique(['access_token']);
            $table->dropColumn(['access_token', 'device_pin', 'device_token_hash', 'device_bound_at', 'device_last_seen_at', 'device_user_agent']);
        });
    }
};
