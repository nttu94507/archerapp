<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventStaff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::query()
            ->orderBy('start_date', 'desc')
            ->get();

        $now = Carbon::now();

        $openEvents = $events
            ->filter(function ($event) use ($now) {
                if (!$event->reg_start || !$event->reg_end) {
                    return false;
                }

                $regStart = Carbon::parse($event->reg_start);
                $regEnd   = Carbon::parse($event->reg_end);

                return $now->between($regStart, $regEnd);
            })
            ->sortBy(function ($event) {
                return $event->reg_end ? Carbon::parse($event->reg_end) : Carbon::parse($event->start_date);
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
            'reg_start'  => 'nullable|date',
            'reg_end'    => 'nullable|date|after_or_equal:reg_start',
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
        $event->load([
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

        $regStatus = null;
        if ($regStartAt && $regEndAt) {
            $regStatus = $isBefore ? '尚未開始' : ($isBetween ? '報名中' : '已截止');
        }

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
        $canManage = auth()->check() && \App\Models\EventStaff::query()
                ->where('event_id', $event->id)
                ->where('user_id', auth()->id())
                ->where('status', 'active')
                ->exists();

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
        ]);
    }

    //
}
