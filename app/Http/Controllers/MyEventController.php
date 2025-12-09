<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyEventController extends Controller
{
    public function index(): View
    {
        $userId = Auth::id();

        $registrations = EventRegistration::query()
            ->with('event')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $now = Carbon::now();

        $events = $registrations->map(function (EventRegistration $registration) use ($now) {
            $event = $registration->event;
            $scoreable = false;

            if ($event) {
                $start = $event->start_date ? Carbon::parse($event->start_date) : null;
                $end = $event->end_date ? Carbon::parse($event->end_date) : null;
                $scoreable = $start && $end ? $now->between($start, $end) : false;
            }

            return [
                'event' => $event,
                'registration' => $registration,
                'scoreable' => $scoreable,
            ];
        })->filter(fn ($row) => $row['event']);

        return view('my-events.index', [
            'events' => $events,
        ]);
    }

    public function score(Event $event): View
    {
        $userId = Auth::id();
        $this->ensureRegistration($event, $userId);

        $now = Carbon::now();
        $scoreable = $this->isWithinWindow($event, $now);

        $entries = EventScoreEntry::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->orderBy('end_number')
            ->get();

        $nextEnd = ($entries->max('end_number') ?? 0) + 1;

        return view('my-events.score', [
            'event' => $event,
            'entries' => $entries,
            'scoreable' => $scoreable,
            'nextEnd' => $nextEnd,
        ]);
    }

    public function storeScore(Request $request, Event $event): RedirectResponse
    {
        $userId = Auth::id();
        $this->ensureRegistration($event, $userId);

        if (!$this->isWithinWindow($event, Carbon::now())) {
            return redirect()->route('my-events.score', $event)
                ->with('error', '目前不在可計分時間內。');
        }

        $validated = $request->validate([
            'end_number' => ['required', 'integer', 'min:1'],
            'scores' => ['required', 'array', 'size:6'],
            'scores.*' => ['nullable', 'string', 'max:2'],
        ]);

        $rawScores = array_map(fn ($v) => strtoupper((string)($v ?? '')), $validated['scores']);

        $normalized = array_map(function (string $value) {
            if ($value === 'X') {
                return 10;
            }
            if ($value === 'M' || $value === '') {
                return 0;
            }

            $intVal = (int)$value;

            return max(0, min(10, $intVal));
        }, $rawScores);

        $endTotal = array_sum($normalized);

        EventScoreEntry::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $userId,
                'end_number' => $validated['end_number'],
            ],
            [
                'scores' => $rawScores,
                'end_total' => $endTotal,
            ]
        );

        return redirect()
            ->route('my-events.score', $event)
            ->with('success', "已送出第 {$validated['end_number']} 趟成績");
    }

    private function ensureRegistration(Event $event, int $userId): void
    {
        $exists = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->exists();

        abort_unless($exists, 403, '尚未報名此賽事');
    }

    private function isWithinWindow(Event $event, Carbon $now): bool
    {
        $start = $event->start_date ? Carbon::parse($event->start_date) : null;
        $end = $event->end_date ? Carbon::parse($event->end_date) : null;

        return $start && $end ? $now->between($start, $end) : false;
    }
}
