<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizerSubscription;
use App\Models\Event;
use App\Models\User;
use App\Support\EventPlanCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'created_at'])
            ->with('organizerSubscription')
            ->withMax('archerySessions', 'created_at')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function updateSubscription(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:activate,cancel'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($validated['action'] === 'cancel') {
            $user->organizerSubscription()->update([
                'status' => OrganizerSubscription::STATUS_CANCELLED,
                'ends_at' => now(),
                'auto_renew' => false,
            ]);

            return back()->with('success', "已停止 {$user->display_name} 的主辦方訂閱；既有賽事權益不受影響。");
        }

        $upgradedEvents = DB::transaction(function () use ($user, $validated, $request): int {
            $subscription = $user->organizerSubscription()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_code' => EventPlanCatalog::SUBSCRIPTION,
                    'status' => OrganizerSubscription::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'ends_at' => $validated['ends_at'] ?? null,
                    'auto_renew' => false,
                    'activated_by' => $request->user()->id,
                ]
            );

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
        });

        return back()->with('success', "已啟用 {$user->display_name} 的主辦方訂閱，並解鎖 {$upgradedEvents} 場既有賽事。");
    }
}
