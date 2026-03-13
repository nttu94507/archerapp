<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $query = Event::query();
        $user = $request->user();

        $query->whereHas('staff', function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->where('status', 'active');
        });

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

    public function show(Event $event): RedirectResponse
    {
        return redirect()->route('events.groups.index', $event);
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
