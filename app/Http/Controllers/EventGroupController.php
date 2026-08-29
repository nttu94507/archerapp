<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    //
    public function index(Event $event)
    {
        $this->authorize('manageGroups', $event);
        $groupCreationLocked = $event->scoringSessions()->exists();
        $groupLimit = $event->planLimit('groups');
        $groupLimitReached = $groupLimit !== null && $event->groups()->count() >= $groupLimit;

        return view('event-groups.index', [
            'event'       => $event,
            'groupCreationLocked' => $groupCreationLocked,
            'groupLimitReached' => $groupLimitReached,
            'groupsAll'   => $event->groups()
                ->withCount('registrations')          // => $group->registrations_count
                ->latest()
                ->paginate(20, ['*'], 'allPage'),
            'groupsEvent' => $event->groups()
//                ->registrations()
                ->withCount('registrations')
                ->latest()
                ->paginate(20, ['*'], 'eventPage'),
        ]);
    }

    public function create(Event $event)
    {
        $this->authorize('manageGroups', $event);
        if ($event->scoringSessions()->exists()) {
            return redirect()->route('events.groups.index', $event)
                ->with('error', '賽事已完成排靶，不能再新增組別。');
        }
        $groupLimit = $event->planLimit('groups');
        $currentGroups = $event->groups()->count();
        if ($groupLimit !== null && $currentGroups >= $groupLimit) {
            return redirect()->route('events.groups.index', $event)
                ->with('error', '免費方案最多只能建立 1 個組別，請先升級單場方案或訂閱。');
        }

        return view('event-groups.create', [
            'event' => $event,
            'maxArrows' => $event->planLimit('arrows_per_phase') ?? 180,
            'maxNewGroups' => $groupLimit === null ? null : $groupLimit - $currentGroups,
        ]);
    }

    public function store(Request $req, Event $event)
    {
        $this->authorize('manageGroups', $event);
        if ($event->scoringSessions()->exists()) {
            return redirect()->route('events.groups.index', $event)
                ->with('error', '賽事已完成排靶，不能再新增組別。');
        }

        $groupLimit = $event->planLimit('groups');
        $currentGroups = $event->groups()->count();
        $remainingGroups = $groupLimit === null ? null : max(0, $groupLimit - $currentGroups);
        if ($remainingGroups === 0) {
            return redirect()->route('events.groups.index', $event)
                ->with('error', '免費方案最多只能建立 1 個組別，請先升級單場方案或訂閱。');
        }

        $maxArrows = $event->planLimit('arrows_per_phase') ?? 180;
        $arrowRule = ['required','integer','min:6','max:'.$maxArrows, function ($attribute, $value, $fail) use ($maxArrows) {
            if ($value % 6 !== 0) {
                $fail('箭數需為 6 的倍數');
            }
            if ($maxArrows === 36 && (int) $value > 36) {
                $fail('免費方案最多只能建立 36 箭單局賽事，請先升級單場方案或訂閱。');
            }
        }];

        $data = $req->validate([
            'groups'                       => array_filter(['required','array','min:1', $remainingGroups === null ? null : 'max:'.$remainingGroups]),
            'groups.*.name'                => ['required','string','max:100'],
            'groups.*.bow_type'            => ['nullable','in:recurve,compound,barebow'],
            'groups.*.gender'              => ['required','in:male,female,open'],
            'groups.*.age_class'           => ['nullable','string','max:50'],
            'groups.*.distance'            => ['nullable','string','max:50'],
            'groups.*.arrow_count'         => $arrowRule,
            'groups.*.arrows_per_end'      => ['nullable','integer','in:3,6'],
            'groups.*.quota'               => ['nullable','integer','min:1'],
            'groups.*.fee'                 => ['nullable','integer','min:0'],
            'groups.*.is_team'             => ['boolean'],
            'groups.*.use_custom_reg_window' => ['nullable','boolean'],
            'groups.*.reg_start'           => ['nullable','date','required_if:groups.*.use_custom_reg_window,1','required_with:groups.*.reg_end'],
            'groups.*.reg_end'             => ['nullable','date','required_if:groups.*.use_custom_reg_window,1','required_with:groups.*.reg_start','after_or_equal:groups.*.reg_start'],
        ]);

        DB::transaction(function () use ($event, $data, $groupLimit) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($groupLimit !== null && $event->groups()->count() + count($data['groups']) > $groupLimit) {
                throw ValidationException::withMessages([
                    'groups' => '免費方案最多只能建立 1 個組別，請先升級單場方案或訂閱。',
                ]);
            }
            foreach ($data['groups'] as $g) {
                unset($g['use_custom_reg_window']);
                $event->groups()->create($g);
            }
        });

        return redirect()
            ->route('events.groups.index', $event)
            ->with('success', '已新增組別');
    }

    public function edit(Event $event, EventGroup $group)
    {
        $this->authorizeGroup($event, $group);
        $maxArrows = $event->planLimit('arrows_per_phase') ?? 180;
        return view('event-groups.edit', compact('event','group', 'maxArrows'));
    }

    public function update(Request $req, Event $event, EventGroup $group)
    {
        $this->authorizeGroup($event, $group);
        $maxArrows = $event->planLimit('arrows_per_phase') ?? 180;
        $arrowRule = ['required','integer','min:6','max:'.$maxArrows, function ($attribute, $value, $fail) use ($maxArrows) {
            if ($value % 6 !== 0) {
                $fail('箭數需為 6 的倍數');
            }
            if ($maxArrows === 36 && (int) $value > 36) {
                $fail('免費方案最多只能設定 36 箭單局賽事，請先升級單場方案或訂閱。');
            }
        }];

        $g = $req->validate([
            'name'      => ['required','string','max:100'],
            'bow_type'  => ['nullable','in:recurve,compound,barebow'],
            'gender'    => ['required','in:male,female,open'],
            'age_class' => ['nullable','string','max:50'],
            'distance'  => ['nullable','string','max:50'],
            'arrow_count' => $arrowRule,
            'arrows_per_end' => ['nullable','integer','in:3,6'],
            'quota'     => ['nullable','integer','min:1'],
            'fee'       => ['nullable','integer','min:0'],
            'is_team'   => ['boolean'],
            'use_custom_reg_window' => ['nullable','boolean'],
            'reg_start' => ['nullable','date','required_if:use_custom_reg_window,1','required_with:reg_end'],
            'reg_end'   => ['nullable','date','required_if:use_custom_reg_window,1','required_with:reg_start','after_or_equal:reg_start'],
        ]);

        if (! $req->boolean('use_custom_reg_window')) {
            $g['reg_start'] = null;
            $g['reg_end'] = null;
        }
        unset($g['use_custom_reg_window']);
        $group->update($g);

        return back()->with('success', '已更新組別');
    }

    public function destroy(Event $event, EventGroup $group)
    {
        $this->authorizeGroup($event, $group);
        if ($group->registrations()->exists()) {
            return back()->with('error', '已有報名資料的組別不能刪除，可改為停止報名。');
        }
        $group->delete();
        return back()->with('success', '已刪除組別');
    }

    private function authorizeGroup(Event $event, EventGroup $group): void
    {
        abort_unless($group->event_id === $event->id, 404);
        $this->authorize('manageGroups', $event);
    }
}
