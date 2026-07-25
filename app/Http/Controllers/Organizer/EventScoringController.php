<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventGroup;
use App\Models\EventScoringSession;
use App\Models\EventScoringTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventScoringController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('manageScores', $event);
        $event->load(['groups' => fn ($query) => $query->withCount([
            'registrations as active_registrations_count' => fn ($registration) => $registration->whereIn('status', ['registered', 'checked_in']),
        ])->withCount('scoringSessions')]);
        $sessions = $event->scoringSessions()
            ->with(['group', 'targets.assignments.registration'])
            ->latest()
            ->get();

        return view('organizer.scoring.index', compact('event', 'sessions'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        $validated = $request->validate([
            'event_group_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120'],
            'athletes_per_target' => ['required', 'integer', 'between:2,4'],
        ]);
        try {
            DB::transaction(function () use ($event, $validated, $request): void {
                $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                $group = EventGroup::where('event_id', $event->id)
                    ->whereKey($validated['event_group_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (EventScoringSession::where('event_group_id', $group->id)->exists()) {
                    abort(422, '此組別已完成排靶，不能重複執行。');
                }

                $registrations = $group->registrations()
                    ->whereIn('status', ['registered', 'checked_in'])
                    ->orderBy('name')
                    ->get();
                if ($registrations->isEmpty()) {
                    abort(422, '此組別目前沒有可排靶的選手。');
                }

                $registrationClosedAt = now();
                $lockedEvent->update(['reg_end' => $registrationClosedAt]);

                $session = EventScoringSession::create([
                    'event_id'=>$event->id,
                    'event_group_id'=>$group->id,
                    'name'=>$validated['name'],
                    'total_arrows'=>$group->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36),
                    'arrows_per_end'=>$group->arrows_per_end ?: 6,
                    'athletes_per_target'=>$validated['athletes_per_target'],
                    'status'=>'ready',
                    'created_by'=>$request->user()->id,
                ]);

                foreach ($registrations->chunk($validated['athletes_per_target']) as $targetIndex => $members) {
                    $target = $session->targets()->create([
                        'target_number'=>$targetIndex + 1,
                        'access_token'=>(string) Str::uuid(),
                        'status'=>'ready',
                    ]);
                    foreach ($members->values() as $position => $registration) {
                        $target->assignments()->create([
                            'event_registration_id'=>$registration->id,
                            'position'=>['A','B','C','D'][$position],
                        ]);
                    }
                }

                EventAuditLog::create([
                    'event_id'=>$event->id, 'user_id'=>$request->user()->id,
                    'action'=>'scoring.session_created', 'subject_type'=>EventScoringSession::class,
                    'subject_id'=>$session->id,
                    'metadata'=>[
                        'group_id'=>$group->id,
                        'targets'=>$session->targets()->count(),
                        'athletes'=>$registrations->count(),
                        'registration_closed_at'=>$registrationClosedAt->toIso8601String(),
                    ],
                ]);
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', '排靶完成，賽事報名已自動截止。');
    }

    public function releaseDevice(Request $request, Event $event, EventScoringTarget $target): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($target->session()->where('event_id', $event->id)->exists(), 404);

        DB::transaction(function () use ($event, $target, $request): void {
            $lockedTarget = EventScoringTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $lockedTarget->update([
                'access_token'=>(string) Str::uuid(),
                'device_token_hash'=>null,
                'device_bound_at'=>null,
                'device_last_seen_at'=>null,
                'device_user_agent'=>null,
            ]);

            EventAuditLog::create([
                'event_id'=>$event->id,
                'user_id'=>$request->user()->id,
                'action'=>'scoring.target_device_released',
                'subject_type'=>EventScoringTarget::class,
                'subject_id'=>$target->id,
                'metadata'=>['target'=>$target->target_number],
            ]);
        });

        return back()->with('success', '靶號 '.$target->target_number.' 的舊連結與設備已失效，請使用畫面上的新連結開啟替代設備。');
    }
}
