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
    public function confirm(Event $event, EventGroup $group, Request $request)
    {
        if (! $event->isPublished() || $group->event_id !== $event->id) {
            abort(404);
        }

        $group->setRelation('event', $event);
        if (! $group->isRegistrationOpen()) {
            return redirect()->route('events.show', $event)->with('error', '目前非報名期間。');
        }

        $registered = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('event_group_id', $group->id)
            ->whereIn('status', ['registered', 'checked_in'])
            ->count();

        if ($group->quota !== null && $registered >= $group->quota) {
            return redirect()->route('events.show', $event)->with('error', '此組別名額已滿。');
        }

        $alreadyRegistered = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('event_group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['registered', 'checked_in'])
            ->exists();

        if ($alreadyRegistered) {
            return redirect()->route('events.show', $event)->with('error', '您已報名此組別。');
        }

        return view('events.confirm-registration', compact('event', 'group', 'registered'));
    }

    //
    public function quickRegister(Event $event,EventGroup $group, Request $request)
    {
        $user = $request->user();

        if (! $event->isPublished() || $event->cancelled_at) {
            return back()->with('error', '此賽事目前未開放報名。');
        }

        // 檢查 group 是否屬於該 event
        if ($group->event_id !== $event->id) {
            return back()->with('error', '組別不屬於此賽事。');
        }

        // 檢查報名期間
        $now = now();
        $group->setRelation('event', $event);
        if (! $group->isRegistrationOpen($now)) {
            return back()->with('error', '目前非報名期間。');
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

        try {
            DB::transaction(function () use ($event, $group, $user) {
                $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                if ($lockedEvent->scoringSessions()->exists()) {
                    abort(422, '賽事已完成排靶，報名已截止。');
                }

                $lockedGroup = EventGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
                $current = EventRegistration::where('event_group_id', $group->id)->whereIn('status', ['registered','checked_in'])->count();
                if (!is_null($lockedGroup->quota) && $current >= $lockedGroup->quota) abort(422, '此組別名額已滿。');

                $existing = EventRegistration::where('event_id',$event->id)->where('event_group_id',$group->id)->where('email',$user->email)->first();
                if ($existing) {
                    $existing->update(['user_id'=>$user->id,'name'=>$user->display_name,'status'=>'registered','withdraw_reason'=>null,'withdrawn_at'=>null,'withdrawn_by'=>null]);
                } else {
                    EventRegistration::create(['event_id'=>$event->id,'event_group_id'=>$group->id,'user_id'=>$user->id,'name'=>$user->display_name,'email'=>$user->email,'status'=>'registered']);
                }
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('events.show', $event)->with('success', '報名成功！');
    }

    public function withdraw(EventRegistration $registration, Request $request)
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        abort_unless(in_array($registration->status, ['registered'], true), 422);
        abort_if($registration->event()->whereHas('scoringSessions')->exists(), 422, '賽事已完成排靶，請聯絡主辦方處理退賽。');
        abort_if($registration->checked_in_at !== null, 422, '已完成報到，請聯絡主辦方處理退賽。');
        $registration->update(['status'=>'withdrawn','withdraw_reason'=>$request->input('reason'),'withdrawn_at'=>now(),'withdrawn_by'=>$request->user()->id]);
        return back()->with('success', '已取消報名。');
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
