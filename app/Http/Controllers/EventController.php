<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

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
        $sort = $request->get('sort', 'date');
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
        // 驗證輸入
        $validated = $request->validate([
            'name'       => 'required|string|max:120',
            'date'       => 'required|date',
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

        // 新增資料
        Event::create($validated);

        // 回應 (依需求，可回 JSON 或 redirect)
        return redirect()->route('events.index')
            ->with('success', '賽事新增成功');
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

    //
}
