<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoringSession;
use App\Models\EventScoringTarget;
use App\Models\EventScoringAssignment;
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
    public function index(Event $event): View|RedirectResponse
    {
        $this->authorize('manageScores', $event);
        if ($event->competition_format === 'elimination_only') {
            return redirect()->route('organizer.events.elimination.index', $event)
                ->withErrors(['scoring'=>'純對抗賽不需要資格賽排靶，請直接建立或管理對抗表。']);
        }
        $requiresCheckIn = $event->requiresCheckIn();
        $event->load(['groups' => fn ($query) => $query->withCount([
            'registrations as active_registrations_count' => fn ($registration) => $registration->whereIn('status', ['registered', 'checked_in']),
            'registrations as checked_in_registrations_count' => fn ($registration) => $registration->where('status', 'checked_in')->whereNotNull('checked_in_at'),
            'registrations as unreported_registrations_count' => fn ($registration) => $registration->where('status', 'registered')->whereNull('checked_in_at'),
        ])->withCount('scoringSessions')]);
        $sessions = $event->scoringSessions()
            ->with(['group', 'targets.assignments.registration'])
            ->latest()
            ->get();
        $unreportedRegistrations = $requiresCheckIn ? $event->registrations()
            ->with('event_group')
            ->where('status', 'registered')
            ->whereNull('checked_in_at')
            ->orderBy('name')
            ->get() : collect();
        $participationRoster = $requiresCheckIn ? collect() : $event->registrations()
            ->with('event_group')
            ->whereIn('status', ['registered', 'checked_in'])
            ->orderBy('event_group_id')
            ->orderBy('name')
            ->get();

        return view('organizer.scoring.index', compact('event', 'sessions', 'unreportedRegistrations', 'participationRoster', 'requiresCheckIn'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_if($event->competition_format === 'elimination_only', 422, '純對抗賽不需要資格賽排靶。');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'athletes_per_target' => ['required', 'integer', 'between:2,4'],
            'confirm_unreported' => ['nullable', 'boolean'],
            'participating_registration_ids' => ['nullable', 'array'],
            'participating_registration_ids.*' => ['integer'],
        ]);
        try {
            $summary = DB::transaction(function () use ($event, $validated, $request): array {
                $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                $requiresCheckIn = $lockedEvent->requiresCheckIn();

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

                $allRegistrationsByGroup = $groups->mapWithKeys(fn (EventGroup $group) => [
                    $group->id => EventRegistration::where('event_id', $event->id)
                        ->where('event_group_id', $group->id)
                        ->whereIn('status', ['registered', 'checked_in'])
                        ->orderBy('name')
                        ->lockForUpdate()
                        ->get(),
                ]);

                $excluded = collect();
                $registrationsByGroup = $allRegistrationsByGroup;
                if (! $requiresCheckIn) {
                    $selectedIds = collect($validated['participating_registration_ids'] ?? [])
                        ->map(fn ($id) => (int) $id)
                        ->unique();
                    $eligibleIds = $allRegistrationsByGroup->flatten(1)->pluck('id');
                    abort_if($selectedIds->diff($eligibleIds)->isNotEmpty(), 422, '出賽名單包含不屬於此賽事的選手。');
                    abort_if($selectedIds->isEmpty(), 422, '請至少保留一位出賽選手再進行排靶。');

                    $excluded = $allRegistrationsByGroup->flatten(1)
                        ->reject(fn (EventRegistration $registration) => $selectedIds->contains($registration->id));
                    foreach ($excluded as $registration) {
                        $registration->update(['status'=>'no_show', 'result_status'=>'dns', 'checked_in_at'=>null]);
                    }
                    $registrationsByGroup = $allRegistrationsByGroup->map(
                        fn ($registrations) => $registrations->filter(
                            fn (EventRegistration $registration) => $selectedIds->contains($registration->id)
                        )->values()
                    );
                }

                if ($registrationsByGroup->every(fn ($registrations) => $registrations->isEmpty())) {
                    abort(422, '目前所有組別都沒有可排靶的選手。');
                }

                $unreported = $requiresCheckIn ? $registrationsByGroup->flatten(1)
                    ->filter(fn (EventRegistration $registration) => $registration->status === 'registered' && $registration->checked_in_at === null)
                    : collect();
                if ($unreported->isNotEmpty() && ! ($validated['confirm_unreported'] ?? false)) {
                    abort(422, '目前還有 '.$unreported->count().' 位選手尚未報到，請確認名單後再選擇繼續排靶。');
                }
                foreach ($unreported as $registration) {
                    $registration->update(['status'=>'no_show', 'result_status'=>'dns']);
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

                    $phase = $group->qualificationPhase()->firstOrCreate(
                        [],
                        array_merge($group->qualificationPhaseAttributes(), [
                            'created_by'=>$request->user()->id,
                        ])
                    );
                    $phase->update([
                        'status'=>'ready',
                        'total_arrows'=>$group->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36),
                        'arrows_per_end'=>$group->arrows_per_end ?: 6,
                        'created_by'=>$phase->created_by ?: $request->user()->id,
                    ]);

                    $session = EventScoringSession::create([
                        'event_id'=>$event->id,
                        'event_group_id'=>$group->id,
                        'event_phase_id'=>$phase->id,
                        'name'=>Str::limit($validated['name'].'－'.$group->name, 120, ''),
                        'total_arrows'=>$group->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36),
                        'arrows_per_end'=>$group->arrows_per_end ?: 6,
                        'athletes_per_target'=>$validated['athletes_per_target'],
                        'status'=>'ready',
                        'created_by'=>$request->user()->id,
                    ]);

                    foreach ($registrations->chunk($validated['athletes_per_target']) as $targetIndex => $members) {
                        $allDns = $members->every(fn (EventRegistration $registration) => $registration->status === 'no_show');
                        $target = $session->targets()->create([
                            'target_number'=>$targetIndex + 1,
                            'access_token'=>(string) Str::uuid(),
                            'device_pin'=>(string) random_int(100000, 999999),
                            'status'=>$allDns ? 'dns' : 'ready',
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
                        'dns_count'=>$registrations->where('status', 'no_show')->count(),
                        ],
                    ]);
                }

                return [
                    'groups'=>$createdGroups,
                    'targets'=>$createdTargets,
                    'athletes'=>$assignedAthletes,
                    'skipped_groups'=>$groups->count() - $createdGroups,
                    'dns'=>$unreported->count(),
                    'excluded'=>$excluded->count(),
                ];
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = "已完成 {$summary['groups']} 個組別、{$summary['targets']} 個靶位、{$summary['athletes']} 位選手的排靶，全部組別報名已截止。";
        if ($summary['skipped_groups'] > 0) {
            $message .= "另有 {$summary['skipped_groups']} 個無選手組別已略過。";
        }
        if ($summary['dns'] > 0) {
            $message .= " {$summary['dns']} 位未報到選手已標記為 DNS，保留靶位但不能輸入分數。";
        }
        if ($summary['excluded'] > 0) {
            $message .= " {$summary['excluded']} 位未出賽選手已標記為 DNS，且未占用靶位。";
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

    public function updateTargetNumber(Request $request, Event $event, EventScoringTarget $target): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($target->session()->where('event_id', $event->id)->exists(), 404);
        $data = $request->validate([
            'target_number'=>['required', 'integer', 'between:1,999'],
            'reason'=>['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $event, $target, $data): void {
            $locked = EventScoringTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $newNumber = (int) $data['target_number'];
            $oldNumber = (int) $locked->target_number;
            if ($newNumber === $oldNumber) return;

            $started = $locked->last_completed_end > 0 || ! in_array($locked->status, ['ready', 'dns'], true);
            if ($started) {
                abort_unless($request->user()->can('manageScoreCorrections', $event), 403);
                if (mb_strlen(trim((string) ($data['reason'] ?? ''))) < 3) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['reason'=>'計分開始後調整靶號，請填寫至少 3 個字的原因。']);
                }
            }

            $swap = EventScoringTarget::query()
                ->where('event_scoring_session_id', $locked->event_scoring_session_id)
                ->where('target_number', $newNumber)
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->first();
            if ($swap) $swap->update(['target_number'=>0]);
            $locked->update(array_merge(['target_number'=>$newNumber], $this->freshDeviceAttributes()));
            if ($swap) $swap->update(array_merge(['target_number'=>$oldNumber], $this->freshDeviceAttributes()));

            EventAuditLog::create([
                'event_id'=>$event->id, 'user_id'=>$request->user()->id,
                'action'=>'scoring.target_number_changed', 'subject_type'=>EventScoringTarget::class,
                'subject_id'=>$locked->id,
                'metadata'=>['before'=>$oldNumber, 'after'=>$newNumber, 'swapped_target_id'=>$swap?->id, 'reason'=>$data['reason'] ?? null, 'forced'=>$started],
            ]);
        });

        return back()->with('success', '排名賽靶號已更新；如有交換靶位，兩台設備都需重新掃描。');
    }

    public function updateAssignmentPosition(Request $request, Event $event, EventScoringTarget $target, EventScoringAssignment $assignment): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($target->session()->where('event_id', $event->id)->exists() && $assignment->event_scoring_target_id === $target->id, 404);
        $data = $request->validate([
            'target_number'=>['required', 'integer', 'between:1,999'],
            'position'=>['required', 'in:A,B,C,D'],
            'reason'=>['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $event, $target, $assignment, $data): void {
            $source = EventScoringTarget::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $moving = EventScoringAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $destination = EventScoringTarget::query()
                ->where('event_scoring_session_id', $source->event_scoring_session_id)
                ->where('target_number', (int) $data['target_number'])
                ->lockForUpdate()
                ->first();
            if (! $destination) {
                $destination = EventScoringTarget::create([
                    'event_scoring_session_id'=>$source->event_scoring_session_id,
                    'target_number'=>(int) $data['target_number'],
                    'access_token'=>(string) Str::uuid(), 'device_pin'=>(string) random_int(100000, 999999),
                    'status'=>'ready',
                ]);
            }
            $newPosition = $data['position'];
            if ($destination->id === $source->id && $moving->position === $newPosition) return;

            $started = collect([$source, $destination])->contains(fn ($item) => $item->last_completed_end > 0 || ! in_array($item->status, ['ready', 'dns'], true));
            if ($started) {
                abort_unless($request->user()->can('manageScoreCorrections', $event), 403);
                if (mb_strlen(trim((string) ($data['reason'] ?? ''))) < 3) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['reason'=>'計分開始後調整選手靶位，請填寫至少 3 個字的原因。']);
                }
            }

            $oldPosition = $moving->position;
            $occupant = EventScoringAssignment::query()
                ->where('event_scoring_target_id', $destination->id)
                ->where('position', $newPosition)
                ->whereKeyNot($moving->id)
                ->lockForUpdate()
                ->first();
            if ($occupant) $occupant->update(['position'=>'Z']);
            $moving->update(['event_scoring_target_id'=>$destination->id, 'position'=>$newPosition]);
            if ($occupant) $occupant->update(['event_scoring_target_id'=>$source->id, 'position'=>$oldPosition]);

            $source->update($this->freshDeviceAttributes());
            if ($destination->id !== $source->id) $destination->update($this->freshDeviceAttributes());
            EventAuditLog::create([
                'event_id'=>$event->id, 'user_id'=>$request->user()->id,
                'action'=>'scoring.assignment_position_changed', 'subject_type'=>EventScoringAssignment::class,
                'subject_id'=>$moving->id,
                'metadata'=>[
                    'registration_id'=>$moving->event_registration_id,
                    'before'=>$source->target_number.$oldPosition,
                    'after'=>$destination->target_number.$newPosition,
                    'swapped_assignment_id'=>$occupant?->id,
                    'reason'=>$data['reason'] ?? null, 'forced'=>$started,
                ],
            ]);
        });

        return back()->with('success', '選手靶位已更新；若目標位置原本有人，兩位選手已交換位置。');
    }

    public function updateAssignmentPositions(Request $request, Event $event, EventScoringSession $session): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($session->event_id === $event->id, 404);
        $data = $request->validate([
            'assignments'=>['required', 'array', 'min:1'],
            'assignments.*.target_number'=>['required', 'integer', 'between:1,999'],
            'assignments.*.position'=>['required', 'in:A,B,C,D'],
            'reason'=>['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $event, $session, $data): void {
            $assignments = EventScoringAssignment::query()
                ->whereHas('target', fn ($query) => $query->where('event_scoring_session_id', $session->id))
                ->with('target')->lockForUpdate()->get();
            $submittedIds = collect(array_keys($data['assignments']))->map(fn ($id) => (int) $id)->sort()->values();
            abort_unless($submittedIds->all() === $assignments->pluck('id')->sort()->values()->all(), 422, '靶位資料已變更，請重新整理後再調整。');

            $desired = $assignments->mapWithKeys(fn ($assignment) => [$assignment->id=>[
                'target_number'=>(int) $data['assignments'][$assignment->id]['target_number'],
                'position'=>$data['assignments'][$assignment->id]['position'],
            ]]);
            if ($desired->map(fn ($item) => $item['target_number'].$item['position'])->duplicates()->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['assignments'=>'同一個靶位位置不能安排兩位選手。']);
            }
            $changed = $assignments->filter(fn ($assignment) =>
                $assignment->target->target_number !== $desired[$assignment->id]['target_number']
                || $assignment->position !== $desired[$assignment->id]['position']
            );
            if ($changed->isEmpty()) return;

            $started = $changed->contains(fn ($assignment) => $assignment->target->last_completed_end > 0 || ! in_array($assignment->target->status, ['ready', 'dns'], true));
            if ($started) {
                abort_unless($request->user()->can('manageScoreCorrections', $event), 403);
                if (mb_strlen(trim((string) ($data['reason'] ?? ''))) < 3) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['reason'=>'計分開始後批次調整靶位，請填寫至少 3 個字的原因。']);
                }
            }

            $targets = EventScoringTarget::where('event_scoring_session_id', $session->id)->lockForUpdate()->get()->keyBy('target_number');
            foreach ($desired->pluck('target_number')->unique() as $number) {
                if (! $targets->has($number)) {
                    $targets->put($number, EventScoringTarget::create([
                        'event_scoring_session_id'=>$session->id, 'target_number'=>$number,
                        'access_token'=>(string) Str::uuid(), 'device_pin'=>(string) random_int(100000, 999999), 'status'=>'ready',
                    ]));
                }
            }
            $before = $assignments->mapWithKeys(fn ($assignment) => [
                $assignment->id=>$assignment->target->target_number.$assignment->position,
            ])->all();
            foreach ($assignments->groupBy('event_scoring_target_id') as $items) {
                foreach ($items->values() as $index => $assignment) $assignment->update(['position'=>['W','X','Y','Z'][$index]]);
            }
            foreach ($assignments as $assignment) {
                $destination = $targets[$desired[$assignment->id]['target_number']];
                $assignment->update(['event_scoring_target_id'=>$destination->id, 'position'=>$desired[$assignment->id]['position']]);
            }
            $affectedTargetIds = $assignments->pluck('event_scoring_target_id')->merge($targets->pluck('id'))->unique();
            EventScoringTarget::whereIn('id', $affectedTargetIds)->get()->each(fn ($target) => $target->update($this->freshDeviceAttributes()));
            EventAuditLog::create([
                'event_id'=>$event->id, 'user_id'=>$request->user()->id,
                'action'=>'scoring.assignments_batch_changed', 'subject_type'=>EventScoringSession::class, 'subject_id'=>$session->id,
                'metadata'=>['before'=>$before, 'after'=>$desired->all(), 'reason'=>$data['reason'] ?? null, 'forced'=>$started],
            ]);
        });

        return back()->with('success', '本場次靶位配置已一次更新，受影響設備需重新掃描。');
    }

    private function freshDeviceAttributes(): array
    {
        return [
            'access_token'=>(string) Str::uuid(), 'device_pin'=>(string) random_int(100000, 999999),
            'device_token_hash'=>null, 'device_bound_at'=>null, 'device_last_seen_at'=>null, 'device_user_agent'=>null,
        ];
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
