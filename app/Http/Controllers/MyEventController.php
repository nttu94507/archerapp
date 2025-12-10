<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
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
        $registration = $this->ensureRegistration($event, $userId);

        $now = Carbon::now();
        $scoreable = $this->isWithinWindow($event, $now);
        $finalized = (bool) $registration->score_submitted_at;

        $entries = EventScoreEntry::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->orderBy('end_number')
            ->get()
            ->keyBy('end_number');

        [$totalArrows, $arrowsPerEnd] = $this->resolveArrowSettings($event, $registration);
        $totalEnds = (int) ceil($totalArrows / $arrowsPerEnd);
        $segments = $this->buildSegments($event, $totalArrows, $arrowsPerEnd, $totalEnds);
        $nextEnd = $this->findNextEnd($entries, $totalEnds);
        $allComplete = $entries->count() >= $totalEnds;
        $stats = $this->calculateStats($entries);

        return view('my-events.score', [
            'event' => $event,
            'entries' => $entries,
            'scoreable' => $scoreable,
            'registration' => $registration,
            'finalized' => $finalized,
            'arrowSettings' => [
                'total_arrows' => $totalArrows,
                'arrows_per_end' => $arrowsPerEnd,
                'total_ends' => $totalEnds,
            ],
            'segments' => $segments,
            'nextEnd' => $nextEnd,
            'allComplete' => $allComplete,
            'stats' => $stats,
        ]);
    }

    public function storeScore(Request $request, Event $event): RedirectResponse
    {
        $userId = Auth::id();
        $registration = $this->ensureRegistration($event, $userId);

        if ($registration->score_submitted_at) {
            return redirect()->route('my-events.score', $event)
                ->with('error', '已送出整局成績，無法再修改。');
        }

        if (!$this->isWithinWindow($event, Carbon::now())) {
            return redirect()->route('my-events.score', $event)
                ->with('error', '目前不在可計分時間內。');
        }

        [$totalArrows, $arrowsPerEnd] = $this->resolveArrowSettings($event, $registration);
        $maxEnd = (int) ceil($totalArrows / $arrowsPerEnd);

        $validated = $request->validate([
            'end_number' => ['required', 'integer', 'min:1', 'max:' . $maxEnd],
            'scores' => ['required', 'array', 'size:' . $arrowsPerEnd],
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
            ->with('success', "已儲存第 {$validated['end_number']} 趟成績，整局送出前仍可修改。");
    }

    public function submitAll(Event $event): RedirectResponse
    {
        $userId = Auth::id();
        $registration = $this->ensureRegistration($event, $userId);

        if ($registration->score_submitted_at) {
            return redirect()->route('my-events.score', $event)
                ->with('success', '整局成績已送出。');
        }

        [$totalArrows, $arrowsPerEnd] = $this->resolveArrowSettings($event, $registration);
        $totalEnds = (int) ceil($totalArrows / $arrowsPerEnd);

        $entries = EventScoreEntry::query()
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('end_number');

        foreach (range(1, $totalEnds) as $end) {
            if (!$entries->has($end)) {
                return redirect()->route('my-events.score', $event)
                    ->with('error', '還有趟次未填寫，無法送出整局成績。');
            }
        }

        $registration->update(['score_submitted_at' => Carbon::now()]);

        return redirect()->route('my-events.score', $event)
            ->with('success', '整局成績已送出，將無法再修改。');
    }

    private function ensureRegistration(Event $event, int $userId): EventRegistration
    {
        $registration = EventRegistration::query()
            ->with('event_group')
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->first();

        abort_unless($registration, 403, '尚未報名此賽事');

        return $registration;
    }

    private function isWithinWindow(Event $event, Carbon $now): bool
    {
        $start = $event->start_date ? Carbon::parse($event->start_date) : null;
        $end = $event->end_date ? Carbon::parse($event->end_date) : null;

        return $start && $end ? $now->between($start, $end) : false;
    }

    private function resolveArrowSettings(Event $event, EventRegistration $registration): array
    {
        $arrowsPerEnd = 6;
        $default = $event->mode === 'indoor' ? 30 : 36;
        $arrowCount = $registration->event_group?->arrow_count ?: $default;

        return [$arrowCount, $arrowsPerEnd];
    }

    private function buildSegments(Event $event, int $totalArrows, int $arrowsPerEnd, int $totalEnds): array
    {
        $firstSegmentArrows = $event->mode === 'indoor' ? 30 : 36;

        if ($totalArrows > $firstSegmentArrows) {
            $firstEnds = (int) ceil($firstSegmentArrows / $arrowsPerEnd);

            return [
                [
                    'label' => '第 1 局',
                    'start' => 1,
                    'end' => $firstEnds,
                ],
                [
                    'label' => '第 2 局',
                    'start' => $firstEnds + 1,
                    'end' => $totalEnds,
                ],
            ];
        }

        return [[
            'label' => '全程',
            'start' => 1,
            'end' => $totalEnds,
        ]];
    }

    private function findNextEnd(Collection $entries, int $totalEnds): int
    {
        foreach (range(1, $totalEnds) as $end) {
            if (!$entries->has($end)) {
                return $end;
            }
        }

        return $totalEnds;
    }

    private function calculateStats(Collection $entries): array
    {
        $flattened = $entries->flatMap(fn (EventScoreEntry $entry) => $entry->scores ?? []);

        $xCount = 0;
        $tenPlus = 0;
        $totalScore = 0;

        foreach ($flattened as $score) {
            $val = strtoupper((string)($score ?? ''));

            if ($val === '') {
                continue;
            }

            if ($val === 'X') {
                $xCount++;
                $tenPlus++;
                $totalScore += 10;
                continue;
            }

            if ($val === 'M') {
                continue;
            }

            $num = max(0, min(10, (int)$val));

            if ($num === 10) {
                $tenPlus++;
            }

            $totalScore += $num;
        }

        return [
            'x_count' => $xCount,
            'ten_plus' => $tenPlus,
            'total_score' => $totalScore,
            'recorded_arrows' => $flattened->filter(fn ($v) => (string)($v ?? '') !== '')->count(),
        ];
    }
}
