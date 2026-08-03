<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\EventStaff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class EventController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin'])->only([
            'create',
            'store',
            'edit',
        ]);
    }

    public function index(Request $request)
    {
        $events = Event::query()->published()->with('groups')
            ->when($request->filled('q'), fn ($query) => $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->q.'%')->orWhere('organizer', 'like', '%'.$request->q.'%')->orWhere('venue', 'like', '%'.$request->q.'%');
            }))
            ->when($request->filled('mode'), fn ($query) => $query->where('mode', $request->mode))
            ->orderBy('start_date', 'desc')
            ->get();

        $now = Carbon::now();

        $ongoingEvents = $events
            ->filter(function ($event) use ($now) {
                if (!$event->start_date) {
                    return false;
                }

                $start = Carbon::parse($event->start_date)->startOfDay();
                $end   = $event->end_date
                    ? Carbon::parse($event->end_date)->endOfDay()
                    : Carbon::parse($event->start_date)->endOfDay();

                return $now->between($start, $end);
            })
            ->sortBy(function ($event) {
                return Carbon::parse($event->start_date);
            })
            ->values();

        $openEvents = $events
            ->filter(fn ($event) => $event->registrationStatus($now) === 'open')
            ->sortBy(function ($event) {
                return $event->registrationClosesAt() ?? Carbon::parse($event->start_date);
            })
            ->values();

        $upcomingEvents = $events
            ->filter(function ($event) use ($now) {
                return $event->start_date && Carbon::parse($event->start_date)->isFuture();
            })
            ->sortBy(function ($event) {
                return Carbon::parse($event->start_date);
            })
            ->values();

        $pastEvents = $events
            ->filter(function ($event) use ($now) {
                $endDate = $event->end_date ? Carbon::parse($event->end_date) : null;
                $startDate = $event->start_date ? Carbon::parse($event->start_date) : null;

                if ($endDate) {
                    return $endDate->lt($now->startOfDay());
                }

                return $startDate ? $startDate->lt($now->startOfDay()) : false;
            })
            ->sortByDesc(function ($event) {
                return $event->end_date ? Carbon::parse($event->end_date) : Carbon::parse($event->start_date);
            })
            ->values();

        return view('events.index', [
            'ongoingEvents'  => $ongoingEvents,
            'openEvents'     => $openEvents,
            'upcomingEvents' => $upcomingEvents,
            'pastEvents'     => $pastEvents,
        ]);
    }
    /**
     * 儲存新賽事
     */
    public function store(Request $request)
    {
        $user = $request->user();
        // 驗證輸入
        $validated = $request->validate([
            'name'       => 'required|string|max:120',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'mode'       => 'required|in:indoor,outdoor',
            'verified'   => 'boolean',
            'level'      => 'nullable|string|max:50',
            'organizer'  => 'required|string|max:120',
            'reg_start'  => 'nullable|date|required_with:reg_end',
            'reg_end'    => 'nullable|date|required_with:reg_start|after_or_equal:reg_start',
            'venue'      => 'nullable|string|max:255',
            'map_link'   => 'nullable|url',
            'lat'        => 'nullable|numeric|between:-90,90',
            'lng'        => 'nullable|numeric|between:-180,180',
        ]);
        // 正規化 checkbox（未勾不會送值）
        $validated['verified'] = $request->boolean('verified');

        $event = DB::transaction(function () use ($validated, $user) {
            $event = Event::create($validated);

            // 這裡假設你已在 Event 模型有：public function staff(){ return $this->hasMany(EventStaff::class); }
            $event->staff()->create([
                'user_id'     => $user->id,
                'role'        => 'owner',
                'status'      => 'active',
                'invited_by'  => $user->id,     // 可選
                'invited_at'  => now(),         // 可選（留痕）
                'accepted_at' => now(),
            ]);

            return $event;
        });

        return redirect()
            ->route('events.groups.create', $event)
            ->with('success', '賽事已建立，接著新增組別');
    }

    /**
     * (選用) 顯示新增表單
     */
    public function create()
    {
        return view('events.create');
    }

    public function edit(Event $event)
    {
        return view('events.edit', compact('event'));
    }

    public function show(Request $request ,Event $event)
    {
        if (! $event->isPublished()) {
            abort_unless($request->user() && $request->user()->can('viewManagement', $event), 404);
        }
        $event->load([
            'scoringSessions',
            'groups' => function ($q) {
                $q->orderBy('name')
                    // 帶出「有效報名數量」（例如：registered / checked_in 視為占名額）
                    ->withCount(['registrations as registered_count' => function ($r) {
                        $r->whereIn('status', ['registered','checked_in']);
                    }]);
            },
        ]);

        $now        = now();
        $regStartAt = $event->reg_start ? \Illuminate\Support\Carbon::parse($event->reg_start) : null;
        $regEndAt   = $event->reg_end   ? \Illuminate\Support\Carbon::parse($event->reg_end)   : null;

        $isBefore  = $regStartAt && $now->lt($regStartAt);
        $isBetween = $regStartAt && $regEndAt && $now->between($regStartAt, $regEndAt);
        $isAfter   = $regEndAt && $now->gt($regEndAt);
        $registrationLocked = $event->scoringSessions->isNotEmpty();

        $regStatus = match ($event->registrationStatus($now)) {
            'open' => '報名中', 'upcoming' => '尚未開始', 'closed' => '已截止', default => null,
        };

        // 目前登入者已經報名哪些 group（有效狀態）
        $myGroupIds = [];
        $myRegistrations = collect();
        if (auth()->check()) {
            $myGroupIds = \App\Models\EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('user_id', auth()->id())
                ->whereIn('status', ['registered','checked_in'])
                ->pluck('event_group_id')
                ->all();

            $myRegistrations = \App\Models\EventRegistration::query()
                ->with('event_group')
                ->where('event_id', $event->id)
                ->where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // 是否為本賽事工作人員
        $canManage = auth()->check() && auth()->user()->can('viewManagement', $event);

        $eventEndAt = $event->end_date ? Carbon::parse($event->end_date)->endOfDay() : null;
        $isEventFinished = $eventEndAt ? $eventEndAt->lt($now) : false;

        return view('events.show', [
            'event'      => $event,
            'groups'     => $event->groups,
            'regStartAt' => $regStartAt,
            'regEndAt'   => $regEndAt,
            'isBefore'   => $isBefore,
            'isBetween'  => $isBetween,
            'isAfter'    => $isAfter,
            'regStatus'  => $regStatus,
            'canManage'  => $canManage,
            'myGroupIds' => $myGroupIds,
            'myRegistrations' => $myRegistrations,
            'isEventFinished' => $isEventFinished,
            'registrationLocked' => $registrationLocked,
        ]);
    }

    public function live(Request $request, Event $event)
    {
        if (! $event->isPublished()) {
            abort_unless($request->user() && $request->user()->can('viewManagement', $event), 404);
        }
        $event->load('groups');
        $now = Carbon::now();

        $selectedGroupId = $request->input('group');
        $sortDirection = $request->input('sort') === 'asc' ? 'asc' : 'desc';

        $registrations = EventRegistration::query()
            ->with('event_group')
            ->where('event_id', $event->id)
            ->whereIn('status', ['registered', 'checked_in'])
            ->get();

        $scoreEntries = EventScoreEntry::query()
            ->where('event_id', $event->id)
            ->orderBy('end_number')
            ->get()
            ->groupBy('event_registration_id');

        $scoreboard = $registrations->map(function (EventRegistration $registration) use ($scoreEntries) {
            $entries = $scoreEntries->get($registration->id, collect());

            $entriesWithStats = $entries->map(function (EventScoreEntry $entry) {
                $stats = $this->tallyScores($entry->scores ?? []);

                $entry->x_count = $stats['x_count'];
                $entry->ten_plus = $stats['ten_plus'];
                $entry->recorded_arrows = $stats['recorded_arrows'];
                $entry->avg_per_arrow = $stats['recorded_arrows'] > 0
                    ? round($stats['total_score'] / $stats['recorded_arrows'], 2)
                    : null;

                $entry->score_total = $stats['total_score'];

                return $entry;
            });

            $scoreStats = $this->tallyScores($entriesWithStats->flatMap(fn (EventScoreEntry $entry) => $entry->scores ?? [])->all());

            return [
                'registration'  => $registration,
                'entries'       => $entriesWithStats,
                'total_score'   => $entriesWithStats->sum('end_total'),
                'ends_recorded' => $entriesWithStats->count(),
                'arrow_count'   => $scoreStats['recorded_arrows'],
                'last_updated'  => $entriesWithStats->max('updated_at'),
                'group_id'      => $registration->event_group_id,
                'x_count'       => $scoreStats['x_count'],
                'ten_plus'      => $scoreStats['ten_plus'],
                'ten_count'     => $scoreStats['ten_count'],
                'avg_per_arrow' => $scoreStats['recorded_arrows'] > 0
                    ? round($scoreStats['total_score'] / $scoreStats['recorded_arrows'], 2)
                    : null,
            ];
        });

        $flatEntries = $scoreEntries->flatten(1);

        $overallBoard = $scoreboard
            ->filter(fn ($row) => $row['ends_recorded'] > 0)
            ->sortByDesc('total_score')
            ->values();

        $overallSummary = [
            'registrations'    => $registrations->count(),
            'groups'           => $event->groups->count(),
            'entry_records'    => $flatEntries->count(),
            'arrows_recorded'  => $flatEntries->reduce(fn (int $carry, EventScoreEntry $entry) => $carry + count($entry->scores ?? []), 0),
            'last_updated'     => $flatEntries->max('updated_at'),
        ];

        $groupedBoards = $scoreboard
            ->groupBy('group_id')
            ->map(function (Collection $rows) use ($event, $sortDirection) {
                $dnfRows = $rows->filter(fn (array $row) => $row['registration']->result_status === 'dnf')
                    ->map(function (array $row): array {
                        $row['rank_position'] = 'DNF';
                        return $row;
                    })->values();
                $ranked = $rows->reject(fn (array $row) => $row['registration']->result_status === 'dnf')->sort(function (array $left, array $right): int {
                    return [$right['total_score'], $right['ten_count'], $right['x_count']]
                        <=> [$left['total_score'], $left['ten_count'], $left['x_count']];
                })->values();

                $previousSignature = null;
                $currentRank = 1;
                $ranked = $ranked->map(function ($row, $idx) use (&$previousSignature, &$currentRank) {
                    $signature = [$row['total_score'], $row['ten_count'], $row['x_count']];
                    if ($previousSignature !== null && $signature !== $previousSignature) {
                        $currentRank = $idx + 1;
                    }
                    $row['rank_position'] = $currentRank;
                    $previousSignature = $signature;
                    return $row;
                });

                $sortedRanked = $sortDirection === 'asc'
                    ? $ranked->sortBy('total_score')->values()
                    : $ranked;
                $sorted = $sortedRanked->concat($dnfRows)->values();

                $firstRow = $rows->first();
                /** @var EventGroup|null $group */
                $group = $firstRow['registration']->event_group ?? null;
                [$arrowsPerEnd, $totalArrows, $totalEnds] = $this->resolveGroupArrowSettings($event, $group);

                $groupEntries = $sorted->flatMap(fn ($row) => $row['entries']);
                $bestEnd = $groupEntries->sortByDesc('end_total')->first();

                $analysis = [
                    'average_total'   => $sorted->count() ? round($sorted->avg('total_score'), 1) : null,
                    'completion_rate' => $sorted->count() && $totalEnds > 0
                        ? round(($sorted->sum('ends_recorded') / ($sorted->count() * $totalEnds)) * 100)
                        : null,
                    'best_end'        => $bestEnd,
                    'recent_update'   => $groupEntries->max('updated_at'),
                    'total_ends'      => $totalEnds,
                ];

                $maxEndsRecorded = $sorted->max('ends_recorded');
                $status = 'not_started';

                if ($totalEnds === 0 || $maxEndsRecorded === 0) {
                    $status = 'not_started';
                } elseif ($maxEndsRecorded < $totalEnds) {
                    $status = 'in_progress';
                } else {
                    $status = 'finished';
                }

                $progress = $totalEnds > 0 ? round(min($maxEndsRecorded / $totalEnds, 1) * 100) : null;

                $statusLabel = match ($status) {
                    'in_progress' => '正在進行',
                    'finished'    => '已結束',
                    default       => '尚未開始',
                };

                return [
                    'group'        => $group,
                    'rows'         => $sorted,
                    'leader'       => $ranked->first(),
                    'analysis'     => $analysis,
                    'totalEnds'    => $totalEnds,
                    'arrowsPerEnd' => $arrowsPerEnd,
                    'totalArrows'  => $totalArrows,
                    'status'       => $status,
                    'status_label' => $statusLabel,
                    'progress'     => $progress,
                ];
            })
            ->sortBy(fn ($group) => $group['group']?->name ?? '未分組')
            ->values();

        $eventEndAt = $event->end_date ? Carbon::parse($event->end_date)->endOfDay() : null;
        $eventFinished = (
            $groupedBoards->isNotEmpty() &&
            $groupedBoards->every(fn ($group) => $group['status'] === 'finished')
        ) || ($eventEndAt ? $now->gt($eventEndAt) : false);

        $groupLeaders = $groupedBoards
            ->map(function (array $board) {
                $leader = $board['leader'];

                if (!$leader) {
                    return null;
                }

                return [
                    'group'         => $board['group'],
                    'registration'  => $leader['registration'],
                    'total_score'   => $leader['total_score'],
                    'ends_recorded' => $leader['ends_recorded'],
                    'arrow_count'   => $leader['arrow_count'],
                    'last_updated'  => $leader['last_updated'],
                ];
            })
            ->filter()
            ->values();

        $statusPriority = [
            'finished'    => 0,
            'not_started' => 1,
            'in_progress' => 2,
        ];

        $activeGroup = $groupedBoards
            ->sortByDesc(function ($group) use ($statusPriority) {
                $priority = $statusPriority[$group['status']] ?? 0;
                $recent   = optional($group['analysis']['recent_update'])->getTimestamp() ?? 0;

                return ($priority * 1_000_000) + $recent;
            })
            ->first();

        $selectedBoard = $selectedGroupId
            ? $groupedBoards->first(function ($board) use ($selectedGroupId) {
                return optional($board['group'])->id == $selectedGroupId;
            })
            : null;

        return view('events.live', [
            'event'          => $event,
            'groupsBoard'    => $groupedBoards,
            'overallBoard'   => $overallBoard,
            'overallSummary' => $overallSummary,
            'groupLeaders'   => $groupLeaders,
            'activeGroup'    => $activeGroup,
            'selectedBoard'  => $selectedBoard,
            'selectedGroupId' => $selectedGroupId,
            'sortDirection' => $sortDirection,
            'eventFinished'  => $eventFinished,
        ]);
    }

    private function resolveGroupArrowSettings(Event $event, ?EventGroup $group): array
    {
        $arrowsPerEnd = 6;
        $defaultTotal = $event->mode === 'indoor' ? 30 : 36;
        $totalArrows = $group?->arrow_count ?: $defaultTotal;

        return [$arrowsPerEnd, $totalArrows, (int) ceil($totalArrows / $arrowsPerEnd)];
    }

    private function tallyScores(iterable $scores): array
    {
        $xCount = 0;
        $tenCount = 0;
        $tenPlus = 0;
        $totalScore = 0;
        $recorded = 0;

        foreach ($scores as $score) {
            $val = strtoupper((string)($score ?? ''));

            if ($val === '') {
                continue;
            }

            $recorded++;

            if ($val === 'X') {
                $xCount++;
                $tenPlus++;
                $totalScore += 10;
                continue;
            }

            if ($val === 'M') {
                continue;
            }

            $num = max(0, min(10, (int) $val));

            if ($num === 10) {
                $tenCount++;
                $tenPlus++;
            }

            $totalScore += $num;
        }

        return [
            'x_count' => $xCount,
            'ten_count' => $tenCount,
            'ten_plus' => $tenPlus,
            'total_score' => $totalScore,
            'recorded_arrows' => $recorded,
        ];
    }

    //
}
