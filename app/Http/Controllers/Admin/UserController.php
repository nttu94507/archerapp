<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizerSubscription;
use App\Models\User;
use App\Services\OrganizerSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    public function updateSubscription(Request $request, User $user, OrganizerSubscriptionService $subscriptions): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:activate,cancel,sync'],
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

        if ($validated['action'] === 'sync') {
            $subscription = $user->activeOrganizerSubscription();
            if (! $subscription) {
                return back()->withErrors(['subscription' => '此帳號目前沒有有效訂閱，無法同步賽事權益。']);
            }

            $upgradedEvents = $subscriptions->syncExistingEvents($user, $subscription);

            return back()->with('success', "已同步 {$user->display_name} 的訂閱權益，並解鎖 {$upgradedEvents} 場既有賽事。");
        }

        $result = $subscriptions->activate(
            $user,
            $request->user(),
            isset($validated['ends_at']) ? Carbon::parse($validated['ends_at']) : null,
        );

        return back()->with('success', "已啟用 {$user->display_name} 的主辦方訂閱，並解鎖 {$result['upgraded_events']} 場既有賽事。");
    }
}
