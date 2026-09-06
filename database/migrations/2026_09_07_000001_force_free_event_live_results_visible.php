<?php

use App\Support\EventPlanCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_groups', 'live_results_visible')) return;

        DB::table('event_groups')
            ->whereIn('event_id', DB::table('events')->select('id')->where('plan_code', EventPlanCatalog::FREE))
            ->update(['live_results_visible'=>true]);
    }

    public function down(): void
    {
        // 公開狀態屬於賽事資料，不在回滾時覆寫使用者後續設定。
    }
};
