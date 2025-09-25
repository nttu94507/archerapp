<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventGroupController extends Controller
{
    //
    public function index(Event $event)
    {
        return view('event-groups.index', [
            'event'  => $event,
            'groups' => $event->groups()->latest()->paginate(20),
        ]);
    }

    public function create(Event $event)
    {
        return view('event-groups.create', ['event' => $event]);
    }

    public function store(Request $req, Event $event)
    {
        $data = $req->validate([
            'groups'                       => ['required','array','min:1'],
            'groups.*.name'                => ['required','string','max:100'],
            'groups.*.bow_type'            => ['nullable','in:recurve,compound,barebow'],
            'groups.*.gender'              => ['required','in:male,female,open'],
            'groups.*.age_class'           => ['nullable','string','max:50'],
            'groups.*.distance'            => ['nullable','string','max:50'],
            'groups.*.quota'               => ['nullable','integer','min:1'],
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

    public function edit(Event $event, EventGroup $group)
    {
        return view('event-groups.edit', compact('event','group'));
    }

    public function update(Request $req, Event $event, EventGroup $group)
    {
        $g = $req->validate([
            'name'      => ['required','string','max:100'],
            'bow_type'  => ['nullable','in:recurve,compound,barebow'],
            'gender'    => ['required','in:male,female,open'],
            'age_class' => ['nullable','string','max:50'],
            'distance'  => ['nullable','string','max:50'],
            'quota'     => ['nullable','integer','min:1'],
            'fee'       => ['nullable','integer','min:0'],
            'is_team'   => ['boolean'],
            'reg_start' => ['nullable','date'],
            'reg_end'   => ['nullable','date','after_or_equal:reg_start'],
        ]);

        $group->update($g);

        return back()->with('success', '已更新組別');
    }

    public function destroy(Event $event, EventGroup $group)
    {
        $group->delete();
        return back()->with('success', '已刪除組別');
    }
}
