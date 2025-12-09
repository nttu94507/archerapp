<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Score;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $query = Event::query();

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->q.'%')
                    ->orWhere('organizer', 'like', '%'.$request->q.'%')
                    ->orWhere('venue', 'like', '%'.$request->q.'%');
            });
        }

        if ($request->filled('mode')) {
            $query->where('mode', $request->mode);
        }

        if ($request->filled('verified')) {
            $query->where('verified', $request->verified);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->date_to);
        }

        $sort = $request->get('sort', 'start_date');
        $dir = $request->get('dir', 'desc');
        if (!in_array($sort, ['start_date', 'end_date', 'created_at'], true)) {
            $sort = 'start_date';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $events = $query
            ->withCount(['groups', 'staff'])
            ->orderBy($sort, $dir)
            ->paginate(15)
            ->withQueryString();

        return view('admin.events.index', [
            'events' => $events,
            'filters' => $request->only(['q', 'mode', 'verified', 'date_from', 'date_to', 'sort', 'dir']),
        ]);
    }

    public function create(): View
    {
        return view('admin.events.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:120',
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'mode'       => 'required|in:indoor,outdoor',
            'verified'   => 'boolean',
            'level'      => 'nullable|string|max:50',
            'organizer'  => 'required|string|max:120',
            'reg_start'  => 'nullable|date',
            'reg_end'    => 'nullable|date|after_or_equal:reg_start',
            'venue'      => 'nullable|string|max:255',
            'map_link'   => 'nullable|url',
            'lat'        => 'nullable|numeric|between:-90,90',
            'lng'        => 'nullable|numeric|between:-180,180',
        ]);

        $validated['verified'] = $request->boolean('verified');

        $event = DB::transaction(function () use ($validated, $request) {
            $event = Event::create($validated);

            $event->staff()->create([
                'user_id'     => $request->user()->id,
                'role'        => 'owner',
                'status'      => 'active',
                'invited_by'  => $request->user()->id,
                'invited_at'  => now(),
                'accepted_at' => now(),
            ]);

            return $event;
        });

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', '賽事已建立，您可以在此頁面管理報名與成績。');
    }

    public function show(Event $event, Request $request): View
    {
        $event->load('groups');

        $participantStatuses = [
            'pending'    => '待處理',
            'registered' => '已報名',
            'checked_in' => '已報到',
            'withdrawn'  => '已退出',
        ];

        $participantQuery = EventRegistration::query()
            ->with('event_group')
            ->where('event_id', $event->id);

        if ($request->filled('participant_q')) {
            $participantQuery->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->participant_q.'%')
                    ->orWhere('email', 'like', '%'.$request->participant_q.'%')
                    ->orWhere('phone', 'like', '%'.$request->participant_q.'%')
                    ->orWhere('team_name', 'like', '%'.$request->participant_q.'%');
            });
        }

        if ($request->filled('participant_status') && isset($participantStatuses[$request->participant_status])) {
            $participantQuery->where('status', $request->participant_status);
        }

        $participants = $participantQuery
            ->orderBy('event_group_id')
            ->orderBy('created_at', 'desc')
            ->get();

        $groupedParticipants = $event->groups
            ->mapWithKeys(function ($group) use ($participants) {
                $registrations = $participants->where('event_group_id', $group->id);

                return [$group->name => $registrations];
            })
            ->filter(fn ($group) => $group->isNotEmpty());

        $unassigned = $participants->whereNull('event_group_id');
        if ($unassigned->isNotEmpty()) {
            $groupedParticipants = $groupedParticipants->put('未指定組別', $unassigned)->sortKeys();
        }

        $groupStats = $groupedParticipants->map(function ($group) {
            return [
                'total'  => $group->count(),
                'paid'   => $group->where('paid', true)->count(),
                'unpaid' => $group->where('paid', false)->count(),
            ];
        });

        $statusCounts = EventRegistration::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->where('event_id', $event->id)
            ->groupBy('status')
            ->pluck('total', 'status');

        $summary = [
            'groups'        => $event->groups->count(),
            'registrations' => EventRegistration::where('event_id', $event->id)->count(),
            'checked_in'    => (int) ($statusCounts['checked_in'] ?? 0),
            'score_entries' => Score::where('event_id', $event->id)->count(),
        ];

        $statSort = $request->get('stat_sort', 'total_score');
        $statDir  = $request->get('stat_dir', 'desc');
        $statSorts = ['total_score', 'x_count', 'ten_count', 'arrow_count', 'stdev', 'scored_at'];
        if (!in_array($statSort, $statSorts, true)) {
            $statSort = 'total_score';
        }
        if (!in_array($statDir, ['asc', 'desc'], true)) {
            $statDir = 'desc';
        }

        $scores = Score::query()
            ->with(['archer', 'round'])
            ->where('event_id', $event->id)
            ->orderBy($statSort, $statDir)
            ->orderBy('total_score', 'desc')
            ->orderBy('x_count', 'desc')
            ->get();

        $leaderboard = $scores->values()->map(function (Score $score, int $index) {
            $score->rank_position = $index + 1;

            return $score;
        });

        $bracketSeeds = $leaderboard->take(8);
        if ($bracketSeeds->count() % 2 === 1) {
            $bracketSeeds = $bracketSeeds->slice(0, $bracketSeeds->count() - 1);
        }
        $bracket = $bracketSeeds->chunk(2)->map(function ($pair, $idx) {
            return [
                'match' => $idx + 1,
                'a'     => $pair->get(0),
                'b'     => $pair->get(1),
            ];
        });

        return view('admin.events.show', [
            'event'                => $event,
            'participants'         => $participants,
            'participantStatuses'  => $participantStatuses,
            'statusCounts'         => $statusCounts,
            'participantStatus'    => $request->participant_status,
            'leaderboard'          => $leaderboard,
            'statSort'             => $statSort,
            'statDir'              => $statDir,
            'summary'              => $summary,
            'bracket'              => $bracket,
            'groupedParticipants'  => $groupedParticipants,
            'groupStats'           => $groupStats,
        ]);
    }

    public function updatePayment(Event $event, EventRegistration $registration, Request $request): RedirectResponse
    {
        if ($registration->event_id !== $event->id) {
            return back()->with('error', '報名資料與賽事不符。');
        }

        $validated = $request->validate([
            'paid' => ['required', 'boolean'],
        ]);

        $registration->update(['paid' => $validated['paid']]);

        return back()->with('success', '繳費狀態已更新。');
    }
}
