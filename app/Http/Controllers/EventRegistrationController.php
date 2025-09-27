<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventRegistrationController extends Controller
{
    //
    public function quickRegister(Event $event,EventGroup $group, Request $request)
    {
        // 組別必須屬於該活動
//        dd($group->event_id);
        abort_unless($group->event_id === $event->id, 404);
//        dd(123);
        $user = $request->user();
        if (!$user->name || !$user->email) {
            // 若個資未齊，導去完整報名頁（或個資頁）
            return redirect()->route('events.list')
                ->with('error', '請先補齊個人資料再報名。');
        }

        // 報名期間（優先組別，無則吃活動級）
        [$start, $end] = [
            $group->reg_start ?: $event->reg_start,
            $group->reg_end   ?: $event->reg_end,
        ];
//        dd(1231);
        $now = now();
        if ($start && $now->lt($start)) return back()->with('error', '報名尚未開始');
        if ($end   && $now->gt($end))   return back()->with('error', '報名已截止');

        // 名額（已報名/已報到才佔名額）
        if (!is_null($group->quota)) {
            $current = \App\Models\EventRegistration::where('event_group_id', $group->id)
                ->whereIn('status', ['registered','checked_in'])->count();
            if ($current >= $group->quota) {
                return back()->with('error', '本組名額已滿');
            }
        }

        // 防重複（同 event+group+email）
        $exists = \App\Models\EventRegistration::where([
            'event_id' => $event->id,
            'event_group_id' => $group->id,
            'email' => $user->email,
        ])->whereIn('status', ['registered','checked_in'])->exists();
        if ($exists) return back()->with('error', '你已報名此組別');

        // 建立報名（使用者資料直接帶入）
        \DB::transaction(function () use ($event, $group, $user) {
            \App\Models\EventRegistration::create([
                'event_id'       => $event->id,
                'event_group_id' => $group->id,
                'user_id'        => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone ?? null, // 若有此欄位
                'team_name'      => null,
                'status'         => 'registered',
                'paid'           => false,
            ]);
        });

        return redirect()->route('events.list', $event)
            ->with('success', '已為你報名「'.$group->name.'」。');
    }

    public function register(Event $event, Request $request)
    {
        $validated = $request->validate([
            'event_group_id' => ['required', Rule::exists('event_groups','id')->where('event_id',$event->id)],
            'name'  => ['required','string','max:120'],
            'email' => ['required','email','max:255'],
            'phone' => ['nullable','string','max:50'],
            'team_name' => ['nullable','string','max:120'],
        ]);

        $group = EventGroup::where('event_id', $event->id)->findOrFail($validated['event_group_id']);

        // 報名期間檢查（優先組別）
        [$regStart, $regEnd] = $this->resolveRegWindow($event, $group);
        $now = now();
        if ($regStart && $now->lt($regStart)) {
            return back()->withInput()->with('error', '報名尚未開始（開始：'.$regStart->format('m/d H:i').'）');
        }
        if ($regEnd && $now->gt($regEnd)) {
            return back()->withInput()->with('error', '報名已截止（截止：'.$regEnd->format('m/d H:i').'）');
        }

        // 名額檢查（只算 registered/checked_in）
        if (!is_null($group->quota)) {
            $current = EventRegistration::where('event_group_id', $group->id)
                ->whereIn('status', ['registered','checked_in'])
                ->count();
            if ($current >= $group->quota) {
                return back()->withInput()->with('error', '本組名額已滿');
            }
        }

        // 防重複（同 event + group + email）
        $exists = EventRegistration::where('event_id', $event->id)
            ->where('event_group_id', $group->id)
            ->where('email', $validated['email'])
            ->whereIn('status', ['registered','checked_in']) // 已報名或已報到視為佔位
            ->exists();
        if ($exists) {
            return back()->withInput()->with('error', '此 Email 已報名該組別，請勿重複報名');
        }

        // 建立報名
        DB::transaction(function () use ($event, $group, $validated, $request) {
            EventRegistration::create([
                'event_id'       => $event->id,
                'event_group_id' => $group->id,
                'user_id'        => optional($request->user())->id,
                'name'           => $validated['name'],
                'email'          => $validated['email'],
                'phone'          => $validated['phone'] ?? null,
                'team_name'      => $validated['team_name'] ?? null,
                'status'         => 'registered', // 改用你的列舉
                'paid'           => false,
            ]);
        });

        return redirect()
            ->route('events.show', $event)
            ->with('success', '報名成功！我們已收到你的資料（組別：'.$group->name.'）。');
    }

    private function resolveRegWindow(Event $event, EventGroup $group): array
    {
        $start = $group->reg_start ?: $event->reg_start;
        $end   = $group->reg_end   ?: $event->reg_end;
        return [
            $start ? Carbon::parse($start) : null,
            $end   ? Carbon::parse($end)   : null,
        ];
    }

    private function resolveGroupOrEventRegWindow(Event $event): array
    {
        $start = $event->reg_start;
        $end   = $event->reg_end;
        return [
            $start ? Carbon::parse($start) : null,
            $end   ? Carbon::parse($end)   : null,
        ];
    }
}
