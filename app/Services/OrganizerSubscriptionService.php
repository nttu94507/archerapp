<?php

namespace App\Services;

use App\Models\Event;
use App\Models\OrganizerSubscription;
use App\Models\User;
use App\Support\EventPlanCatalog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class OrganizerSubscriptionService
{
    /** @return array{subscription: OrganizerSubscription, upgraded_events: int} */
    public function activate(User $user, User $actor, ?CarbonInterface $endsAt = null): array
    {
        return DB::transaction(function () use ($user, $actor, $endsAt): array {
            $subscription = $user->organizerSubscription()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_code' => EventPlanCatalog::SUBSCRIPTION,
                    'status' => OrganizerSubscription::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'ends_at' => $endsAt,
                    'auto_renew' => false,
                    'activated_by' => $actor->id,
                ]
            );

            return [
                'subscription' => $subscription,
                'upgraded_events' => $this->syncExistingEvents($user, $subscription),
            ];
        });
    }

    public function syncExistingEvents(User $user, OrganizerSubscription $subscription): int
    {
        abort_unless($subscription->user_id === $user->id && $subscription->isActive(), 422, '訂閱目前無效，無法同步賽事權益。');

        return Event::query()
            ->where('plan_code', EventPlanCatalog::FREE)
            ->whereHas('staff', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('role', 'owner')
                ->where('status', 'active'))
            ->update([
                'plan_code' => EventPlanCatalog::SUBSCRIPTION,
                'plan_status' => EventPlanCatalog::STATUS_ACTIVE,
                'plan_limits_snapshot' => json_encode(EventPlanCatalog::limits(EventPlanCatalog::SUBSCRIPTION)),
                'plan_features_snapshot' => json_encode(EventPlanCatalog::features(EventPlanCatalog::SUBSCRIPTION)),
                'plan_activated_at' => now(),
                'plan_expires_at' => null,
                'plan_order_reference' => 'subscription:'.$subscription->id,
            ]);
    }
}
