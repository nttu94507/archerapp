<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $events = Event::query()
            ->whereHas('staff', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'manager']))
            ->latest('start_date')
            ->get();

        $selectedEvent = $events->firstWhere('uuid', $request->string('event')->toString());
        $upgradeableEvents = $events->filter->canUpgradeToEventPass()->values();
        $unavailableFreeEvents = $events->filter(
            fn (Event $event) => $event->isFreePlan() && ! $event->canUpgradeToEventPass()
        )->values();
        $subscription = $request->user()->activeOrganizerSubscription();

        return view('store.index', compact('events', 'selectedEvent', 'upgradeableEvents', 'unavailableFreeEvents', 'subscription'));
    }
}
