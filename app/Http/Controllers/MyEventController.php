<?php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use Illuminate\Support\Collection;
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

        $events = $registrations->map(function (EventRegistration $registration) {
            $event = $registration->event;

            return [
                'event' => $event,
                'registration' => $registration,
            ];
        })->filter(fn ($row) => $row['event']);

        return view('my-events.index', [
            'events' => $events,
        ]);
    }

    public function result(EventRegistration $registration): View
    {
        abort_unless($registration->user_id === Auth::id(), 403);
        abort_unless($registration->result_published_at !== null, 404);

        $registration->load([
            'event',
            'event_group',
            'scoreEntries' => fn ($query) => $query->orderBy('end_number'),
            'scoringAssignment.target',
        ]);

        $stats = $this->scoreStats($registration->scoreEntries);
        $ranking = EventRegistration::query()
            ->where('event_id', $registration->event_id)
            ->where('event_group_id', $registration->event_group_id)
            ->whereIn('status', ['registered', 'checked_in'])
            ->where(fn ($query) => $query->whereNull('result_status')->orWhereNotIn('result_status', ['dnf', 'dns']))
            ->whereNotNull('result_published_at')
            ->with('scoreEntries')
            ->get()
            ->map(function (EventRegistration $item): array {
                return ['registration'=>$item] + $this->scoreStats($item->scoreEntries);
            })
            ->sort(function (array $left, array $right): int {
                return [$right['total'], $right['ten_count'], $right['x_count']]
                    <=> [$left['total'], $left['ten_count'], $left['x_count']];
            })
            ->values();

        $rank = in_array($registration->result_status, ['dnf', 'dns'], true) ? null : 1;
        $previous = null;
        foreach ($ranking as $index => $row) {
            if ($rank === null) break;
            $signature = [$row['total'], $row['ten_count'], $row['x_count']];
            if ($previous !== null && $signature !== $previous) {
                $rank = $index + 1;
            }
            if ($row['registration']->is($registration)) {
                break;
            }
            $previous = $signature;
        }

        return view('my-events.result', compact('registration', 'stats', 'rank'));
    }

    private function scoreStats(Collection $entries): array
    {
        $scores = $entries->flatMap(fn ($entry) => $entry->scores ?? []);

        return [
            'total'=>$entries->sum('end_total'),
            'ten_count'=>$scores->filter(fn ($score) => (string) $score === '10')->count(),
            'x_count'=>$scores->filter(fn ($score) => strtoupper((string) $score) === 'X')->count(),
        ];
    }

}
