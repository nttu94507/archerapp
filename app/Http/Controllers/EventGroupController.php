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
            'existingGroupKeys' => $event->groups()
                ->get(['bow_type', 'distance', 'gender', 'age_class'])
                ->map(fn (EventGroup $group) => EventGroup::duplicateKey($group->bow_type, $group->distance, $group->gender, $group->age_class))
                ->all(),
            'existingGroupNames' => $event->groups()->pluck('name')->map(fn ($name) => EventGroup::duplicateName($name))->all(),
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
            'use_first_group_fee'          => ['nullable','boolean'],
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
            'groups.*.standard_team_enabled'=> ['nullable','boolean'],
            'groups.*.mixed_team_enabled'  => ['nullable','boolean'],
            'groups.*.team_size'           => ['nullable','integer','in:3'],
            'groups.*.team_type'           => ['nullable','in:standard,mixed'],
            'groups.*.team_substitute_limit'=> ['nullable','integer','between:0,1'],
            'groups.*.team_formation_end'  => ['nullable','date'],
            'groups.*.use_custom_reg_window' => ['nullable','boolean'],
            'groups.*.reg_start'           => ['nullable','date','required_if:groups.*.use_custom_reg_window,1','required_with:groups.*.reg_end'],
            'groups.*.reg_end'             => ['nullable','date','required_if:groups.*.use_custom_reg_window,1','required_with:groups.*.reg_start','after_or_equal:groups.*.reg_start'],
        ]);

        if (collect($data['groups'])->contains(fn ($group) => ! empty($group['is_team'])) && ! $event->hasPlanFeature('team_competition')) {
            throw ValidationException::withMessages(['groups'=>'團體賽為單場升級或訂閱方案功能。']);
        }

        if (! empty($data['use_first_group_fee'])) {
            $firstGroupFee = $data['groups'][0]['fee'] ?? 0;
            foreach ($data['groups'] as &$groupData) {
                $groupData['fee'] = $firstGroupFee;
            }
            unset($groupData);
        }
        unset($data['use_first_group_fee']);

        DB::transaction(function () use ($event, $data, $groupLimit) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($groupLimit !== null && $event->groups()->count() + count($data['groups']) > $groupLimit) {
                throw ValidationException::withMessages([
                    'groups' => '免費方案最多只能建立 1 個組別，請先升級單場方案或訂閱。',
                ]);
            }
            $groupKey = fn (array $group): string => EventGroup::duplicateKey(
                $group['bow_type'] ?? null,
                $group['distance'] ?? null,
                $group['gender'] ?? null,
                $group['age_class'] ?? null,
            );
            $existingGroups = $event->groups()->get(['name', 'bow_type', 'distance', 'gender', 'age_class']);
            $existingKeys = $existingGroups
                ->map(fn (EventGroup $group): string => $groupKey($group->toArray()))
                ->all();
            $existingNames = $existingGroups->map(fn (EventGroup $group): string => EventGroup::duplicateName($group->name))->all();
            $pendingKeys = [];
            $pendingNames = [];
            foreach ($data['groups'] as $g) {
                $key = $groupKey($g);
                $name = EventGroup::duplicateName($g['name']);
                if (in_array($key, $existingKeys, true) || in_array($key, $pendingKeys, true)
                    || in_array($name, $existingNames, true) || in_array($name, $pendingNames, true)) {
                    throw ValidationException::withMessages([
                        'groups' => '不能建立重複組別：相同名稱，或相同弓種、距離與性別的組別已存在。',
                    ]);
                }
                $pendingKeys[] = $key;
                $pendingNames[] = $name;
                unset($g['use_custom_reg_window']);
                $g['arrows_per_end'] = $event->mode === 'indoor' ? 3 : 6;
                $g['standard_team_enabled'] = ! empty($g['standard_team_enabled']);
                $g['mixed_team_enabled'] = ! empty($g['mixed_team_enabled']);
                $g['is_team'] = $g['standard_team_enabled'] || $g['mixed_team_enabled'] || ! empty($g['is_team']);
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
            'standard_team_enabled'=>['nullable','boolean'],
            'mixed_team_enabled'=>['nullable','boolean'],
            'team_size' => ['nullable','integer','in:3'],
            'team_type' => ['nullable','in:standard,mixed'],
            'team_substitute_limit' => ['nullable','integer','between:0,1'],
            'team_formation_end' => ['nullable','date'],
            'use_custom_reg_window' => ['nullable','boolean'],
            'reg_start' => ['nullable','date','required_if:use_custom_reg_window,1','required_with:reg_end'],
            'reg_end'   => ['nullable','date','required_if:use_custom_reg_window,1','required_with:reg_start','after_or_equal:reg_start'],
        ]);
        $standardEnabled=$req->boolean('standard_team_enabled');
        $mixedEnabled=$req->boolean('mixed_team_enabled');
        $g['arrows_per_end'] = $event->mode === 'indoor' ? 3 : 6;
        if (($standardEnabled || $mixedEnabled) && ! $event->hasPlanFeature('team_competition')) {
            throw ValidationException::withMessages(['is_team'=>'團體賽為單場升級或訂閱方案功能。']);
        }
        if (! ($standardEnabled || $mixedEnabled) && $group->eventTeams()->where('status','!=','disbanded')->exists()) {
            throw ValidationException::withMessages(['is_team'=>'已有隊伍後不能關閉團體賽，請先處理現有隊伍。']);
        }
        $g['standard_team_enabled']=$standardEnabled;
        $g['mixed_team_enabled']=$mixedEnabled;
        $g['is_team'] = $standardEnabled || $mixedEnabled;
        $g['team_type'] = $g['team_type'] ?? 'standard';
        $g['team_size'] = $g['team_type'] === 'mixed' ? 2 : 3;
        $g['team_substitute_limit'] = (int) ($g['team_substitute_limit'] ?? 0);
        if (! $g['is_team']) $g['team_formation_end'] = null;

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
