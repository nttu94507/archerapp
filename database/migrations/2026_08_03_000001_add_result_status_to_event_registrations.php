<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('result_status', 20)->nullable()->after('result_published_at');
        });

        DB::table('event_registrations')
            ->whereNotNull('score_verified_at')
            ->whereNull('result_status')
            ->update(['result_status'=>'completed']);
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('result_status');
        });
    }
};
