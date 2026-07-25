<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_scoring_targets', function (Blueprint $table) {
            $table->char('device_token_hash', 64)->nullable()->after('access_token');
            $table->timestamp('device_bound_at')->nullable()->after('device_token_hash');
            $table->timestamp('device_last_seen_at')->nullable()->after('device_bound_at');
            $table->string('device_user_agent', 500)->nullable()->after('device_last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_scoring_targets', function (Blueprint $table) {
            $table->dropColumn([
                'device_token_hash',
                'device_bound_at',
                'device_last_seen_at',
                'device_user_agent',
            ]);
        });
    }
};
