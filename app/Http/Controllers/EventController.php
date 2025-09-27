<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query();

        // 篩選條件
        if ($request->filled('q')) {
            $query->where(function($q) use ($request) {
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
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // 排序
        $sort = $request->get('sort', 'start_date');
        $dir  = $request->get('dir', 'desc');
        $query->orderBy($sort, $dir);

        // 這裡很重要：用 paginate，不要用 get()
        $events = $query->paginate(15);

        return view('events.index', compact('events'));
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

    public function register()
    {
        return view('events.register');
    }
    public function edit(Event $event)
    {
        return view('events.edit', compact('event'));
    }

    public function joinevent(Request $request, Event $event){

    }

    //
}
