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
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

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
            'name' => ['required', 'string', 'max:120'],
            'athletes_per_target' => ['required', 'integer', 'between:2,4'],
        ]);
        try {
            $summary = DB::transaction(function () use ($event, $validated, $request): array {
                $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

                if (EventScoringSession::where('event_id', $event->id)->exists()) {
                    abort(422, '此賽事已完成排靶，不能重複執行。');
                }

                $groups = EventGroup::where('event_id', $event->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($groups->isEmpty()) {
                    abort(422, '此賽事尚未建立任何組別。');
                }

                $registrationsByGroup = $groups->mapWithKeys(fn (EventGroup $group) => [
                    $group->id => $group->registrations()
                        ->whereIn('status', ['registered', 'checked_in'])
                        ->orderBy('name')
                        ->get(),
                ]);

                if ($registrationsByGroup->every(fn ($registrations) => $registrations->isEmpty())) {
                    abort(422, '目前所有組別都沒有可排靶的選手。');
                }

                $registrationClosedAt = now();
                $lockedEvent->update(['reg_end' => $registrationClosedAt]);
                EventGroup::where('event_id', $event->id)->update(['reg_end' => $registrationClosedAt]);

                $createdGroups = 0;
                $createdTargets = 0;
                $assignedAthletes = 0;

                foreach ($groups as $group) {
                    $registrations = $registrationsByGroup->get($group->id);
                    if ($registrations->isEmpty()) {
                        continue;
                    }

                    $session = EventScoringSession::create([
                        'event_id'=>$event->id,
                        'event_group_id'=>$group->id,
                        'name'=>Str::limit($validated['name'].'－'.$group->name, 120, ''),
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
                            'device_pin'=>(string) random_int(100000, 999999),
                            'status'=>'ready',
                        ]);
                        foreach ($members->values() as $position => $registration) {
                            $target->assignments()->create([
                                'event_registration_id'=>$registration->id,
                                'position'=>['A','B','C','D'][$position],
                            ]);
                        }
                    }

                    $targetCount = $session->targets()->count();
                    $createdGroups++;
                    $createdTargets += $targetCount;
                    $assignedAthletes += $registrations->count();

                    EventAuditLog::create([
                        'event_id'=>$event->id, 'user_id'=>$request->user()->id,
                        'action'=>'scoring.session_created', 'subject_type'=>EventScoringSession::class,
                        'subject_id'=>$session->id,
                        'metadata'=>[
                            'group_id'=>$group->id,
                            'targets'=>$targetCount,
                            'athletes'=>$registrations->count(),
                            'registration_closed_at'=>$registrationClosedAt->toIso8601String(),
                        ],
                    ]);
                }

                return [
                    'groups'=>$createdGroups,
                    'targets'=>$createdTargets,
                    'athletes'=>$assignedAthletes,
                    'skipped_groups'=>$groups->count() - $createdGroups,
                ];
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = "已完成 {$summary['groups']} 個組別、{$summary['targets']} 個靶位、{$summary['athletes']} 位選手的排靶，全部組別報名已截止。";
        if ($summary['skipped_groups'] > 0) {
            $message .= "另有 {$summary['skipped_groups']} 個無選手組別已略過。";
        }

        return back()->with('success', $message);
    }

    public function releaseDevice(Request $request, Event $event, EventScoringTarget $target): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($target->session()->where('event_id', $event->id)->exists(), 404);

        DB::transaction(function () use ($event, $target, $request): void {
            $lockedTarget = EventScoringTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $lockedTarget->update([
                'access_token'=>(string) Str::uuid(),
                'device_pin'=>(string) random_int(100000, 999999),
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

    public function qrCode(Event $event, EventScoringTarget $target)
    {
        $this->authorize('manageScores', $event);
        abort_unless($target->session()->where('event_id', $event->id)->exists(), 404);

        $renderer = new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString(route('scoring-stations.show', $target->access_token));

        return response($svg, 200, [
            'Content-Type'=>'image/svg+xml',
            'Cache-Control'=>'no-store, private',
        ]);
    }
}
