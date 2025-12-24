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
        $user = $request->user();

        // 檢查 group 是否屬於該 event
        if ($group->event_id !== $event->id) {
            return back()->with('error', '組別不屬於此賽事。');
        }

        // 檢查報名期間
        $now = now();
        $start = $event->reg_start ? Carbon::parse($event->reg_start) : null;
        $end   = $event->reg_end   ? Carbon::parse($event->reg_end)   : null;

        if (!($start && $end && $now->between($start, $end))) {
            return back()->with('error', '目前非報名期間。');
        }

        if ($group->registration_closed) {
            return back()->with('error', '此組別已結束報名。');
        }

        // 檢查是否已報名（有效狀態）
        $exists = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('event_group_id', $group->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['registered','checked_in'])
            ->exists();

        if ($exists) {
            return back()->with('error', '您已報名此組別。');
        }

        // 檢查名額（如果有設定 capacity）
        $group->loadCount(['registrations as registered_count' => function ($q) {
            $q->whereIn('status', ['registered','checked_in']);
        }]);

        if (!is_null($group->quota) && $group->registered_count >= $group->quota) {
            return back()->with('error', '此組別名額已滿。');
        }

        // 建立報名
        EventRegistration::create([
            'event_id'       => $event->id,
            'event_group_id' => $group->id,
            'user_id'        => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'status'         => 'registered',
        ]);

        return redirect()->route('events.show', $event)->with('success', '報名成功！');
    }

//    public function register(Event $event, Request $request)
//    {
//        $validated = $request->validate([
//            'event_group_id' => ['required', Rule::exists('event_groups','id')->where('event_id',$event->id)],
//            'name'  => ['required','string','max:120'],
//            'email' => ['required','email','max:255'],
//            'phone' => ['nullable','string','max:50'],
//            'team_name' => ['nullable','string','max:120'],
//        ]);
//
//        $group = EventGroup::where('event_id', $event->id)->findOrFail($validated['event_group_id']);
//
//        // 報名期間檢查（優先組別）
//        [$regStart, $regEnd] = $this->resolveRegWindow($event, $group);
//        $now = now();
//        if ($regStart && $now->lt($regStart)) {
//            return back()->withInput()->with('error', '報名尚未開始（開始：'.$regStart->format('m/d H:i').'）');
//        }
//        if ($regEnd && $now->gt($regEnd)) {
//            return back()->withInput()->with('error', '報名已截止（截止：'.$regEnd->format('m/d H:i').'）');
//        }
//
//        // 名額檢查（只算 registered/checked_in）
//        if (!is_null($group->quota)) {
//            $current = EventRegistration::where('event_group_id', $group->id)
//                ->whereIn('status', ['registered','checked_in'])
//                ->count();
//            if ($current >= $group->quota) {
//                return back()->withInput()->with('error', '本組名額已滿');
//            }
//        }
//
//        // 防重複（同 event + group + email）
//        $exists = EventRegistration::where('event_id', $event->id)
//            ->where('event_group_id', $group->id)
//            ->where('email', $validated['email'])
//            ->whereIn('status', ['registered','checked_in']) // 已報名或已報到視為佔位
//            ->exists();
//        if ($exists) {
//            return back()->withInput()->with('error', '此 Email 已報名該組別，請勿重複報名');
//        }
//
//        // 建立報名
//        DB::transaction(function () use ($event, $group, $validated, $request) {
//            EventRegistration::create([
//                'event_id'       => $event->id,
//                'event_group_id' => $group->id,
//                'user_id'        => optional($request->user())->id,
//                'name'           => $validated['name'],
//                'email'          => $validated['email'],
//                'phone'          => $validated['phone'] ?? null,
//                'team_name'      => $validated['team_name'] ?? null,
//                'status'         => 'registered', // 改用你的列舉
//                'paid'           => false,
//            ]);
//        });
//
//        return redirect()
//            ->route('events.show', $event)
//            ->with('success', '報名成功！我們已收到你的資料（組別：'.$group->name.'）。');
//    }

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
