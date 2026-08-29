<?php

use App\Support\EventPlanCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $freeLimits = json_encode(EventPlanCatalog::limits(EventPlanCatalog::FREE));
        $freeFeatures = json_encode(EventPlanCatalog::features(EventPlanCatalog::FREE));

        DB::table('events')
            ->where('plan_code', EventPlanCatalog::SUBSCRIPTION)
            ->where('plan_order_reference', 'like', 'subscription:%')
            ->where(function ($historical): void {
                $historical
                    ->whereColumn('cancelled_at', '<=', 'plan_activated_at')
                    ->orWhereColumn('completed_at', '<=', 'plan_activated_at')
                    ->orWhereExists(fn ($audit) => $audit
                        ->selectRaw('1')
                        ->from('event_audit_logs')
                        ->whereColumn('event_audit_logs.event_id', 'events.id')
                        ->where('event_audit_logs.action', 'event.completed')
                        ->whereColumn('event_audit_logs.created_at', '<=', 'events.plan_activated_at'));
            })
            ->update([
                'plan_code' => EventPlanCatalog::FREE,
                'plan_status' => EventPlanCatalog::STATUS_ACTIVE,
                'plan_limits_snapshot' => $freeLimits,
                'plan_features_snapshot' => $freeFeatures,
                'plan_activated_at' => now(),
                'plan_expires_at' => null,
                'plan_order_reference' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // 無法可靠還原哪些歷史賽事曾因訂閱同步升級，因此不自動重新授權。
    }
};
