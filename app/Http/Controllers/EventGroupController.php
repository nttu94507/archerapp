<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    //
    public function index(Event $event)
    {
        return view('event-groups.index', [
            'event'       => $event,
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
        return view('event-groups.create', ['event' => $event]);
    }

    public function show(Event $event, EventGroup $group)
    {
        if ($group->event_id !== $event->id) {
            abort(404);
        }

        return view('event-groups.show', [
            'event'        => $event,
            'group'        => $group,
            'participants' => $group->registrations()->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function store(Request $req, Event $event)
    {
        $arrowRule = ['required','integer','min:6','max:180', function ($attribute, $value, $fail) {
            if ($value % 6 !== 0) {
                $fail('箭數需為 6 的倍數');
            }
        }];

        $data = $req->validate([
            'groups'                       => ['required','array','min:1'],
            'groups.*.name'                => ['required','string','max:100'],
            'groups.*.bow_type'            => ['nullable','in:recurve,compound,barebow'],
            'groups.*.gender'              => ['required','in:male,female,open'],
            'groups.*.age_class'           => ['nullable','string','max:50'],
            'groups.*.distance'            => ['nullable','string','max:50'],
            'groups.*.arrow_count'         => $arrowRule,
            'groups.*.quota'               => ['nullable','integer','min:1'],
            'groups.*.target_slots'        => ['nullable','integer','min:1'],
            'groups.*.fee'                 => ['nullable','integer','min:0'],
            'groups.*.is_team'             => ['boolean'],
            'groups.*.reg_start'           => ['nullable','date'],
            'groups.*.reg_end'             => ['nullable','date','after_or_equal:groups.*.reg_start'],
        ]);

        DB::transaction(function () use ($event, $data) {
            foreach ($data['groups'] as $g) {
                $event->groups()->create($g);
            }
        });

        return redirect()
            ->route('events.groups.index', $event)
            ->with('success', '已新增組別');
    }

    public function destroy(Event $event, EventGroup $group)
    {
        $group->delete();
        $group->registrations()->delete();
        return back()->with('success', '已刪除組別');
    }

    public function closeRegistration(Event $event, EventGroup $group): RedirectResponse
    {
        if ($group->event_id !== $event->id) {
            abort(404);
        }

        if (!$group->target_slots) {
            return back()->with('error', '請先設定靶位數量再結束報名。');
        }

        $group->update(['registration_closed' => true]);

        $paidRegistrations = EventRegistration::query()
            ->where('event_group_id', $group->id)
            ->where('paid', true)
            ->orderBy('created_at')
            ->get();

        $slots = (int) $group->target_slots;
        $paidRegistrations->each(function (EventRegistration $registration, int $index) use ($slots) {
            $targetNumber = ($index % $slots) + 1;
            $letterIndex = intdiv($index, $slots);
            $targetLetter = $this->indexToLetters($letterIndex);

            $registration->update([
                'target_number' => $targetNumber,
                'target_letter' => $targetLetter,
            ]);
        });

        return back()->with('success', '報名已截止，靶位已分配完成。');
    }

    private function indexToLetters(int $index): string
    {
        $letters = '';
        $index += 1;

        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }
}
