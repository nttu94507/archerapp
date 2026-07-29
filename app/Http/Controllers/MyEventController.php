<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyEventController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        $registrations = EventRegistration::query()
            ->with(['event', 'event_group'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $now = Carbon::now();

        $events = $registrations->map(function (EventRegistration $registration) use ($now) {
            $event = $registration->event;

            return [
                'event' => $event,
                'registration' => $registration,
                'phase' => $this->eventPhase($event, $now),
            ];
        })->filter(fn ($row) => $row['event']);

        return view('my-events.index', [
            'events' => $events,
        ]);
    }

    private function eventPhase(Event $event, Carbon $now): string
    {
        if ($event->cancelled_at) return 'cancelled';
        $start = $event->start_date ? Carbon::parse($event->start_date)->startOfDay() : null;
        $end = $event->end_date ? Carbon::parse($event->end_date)->endOfDay() : $start?->copy()->endOfDay();
        if ($start && $now->lt($start)) return 'upcoming';
        if ($start && $end && $now->between($start, $end)) return 'ongoing';
        return 'finished';
    }

}
