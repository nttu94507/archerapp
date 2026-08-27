<?php

use App\Support\EventPlanCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $limits = json_encode(EventPlanCatalog::limits(EventPlanCatalog::SUBSCRIPTION));
        $features = json_encode(EventPlanCatalog::features(EventPlanCatalog::SUBSCRIPTION));

        DB::table('organizer_subscriptions')
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('id')
            ->each(function (object $subscription) use ($now, $limits, $features): void {
                $eventIds = DB::table('event_staff')
                    ->where('user_id', $subscription->user_id)
                    ->where('role', 'owner')
                    ->where('status', 'active')
                    ->pluck('event_id');

                DB::table('events')
                    ->whereIn('id', $eventIds)
                    ->where('plan_code', EventPlanCatalog::FREE)
                    ->update([
                        'plan_code' => EventPlanCatalog::SUBSCRIPTION,
                        'plan_status' => EventPlanCatalog::STATUS_ACTIVE,
                        'plan_limits_snapshot' => $limits,
                        'plan_features_snapshot' => $features,
                        'plan_activated_at' => $now,
                        'plan_expires_at' => null,
                        'plan_order_reference' => 'subscription:'.$subscription->id,
                        'updated_at' => $now,
                    ]);
            });
    }

    public function down(): void
    {
        // 權益快照一旦授予便不自動降級，避免破壞進行中或已完成的賽事。
    }
};
