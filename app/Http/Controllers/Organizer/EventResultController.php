<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\EventScoringSession;
use App\Models\EventRankingSnapshot;
use App\Services\EventBadgeAwardService;
use App\Services\QualificationRankingSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventResultController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('viewResults', $event);
        $registrations = $event->registrations()->with(['event_group', 'scoreEntries', 'scoringAssignment.target'])->whereIn('status', ['registered','checked_in','no_show'])->get()->map(function ($registration) {
            $registration->calculated_total = $registration->scoreEntries->sum('end_total');
            $scores = $registration->scoreEntries->flatMap(fn ($entry) => $entry->scores ?? []);
            $registration->calculated_ten_count = $scores->filter(fn ($score) => (string) $score === '10')->count();
            $registration->calculated_x_count = $scores->filter(fn ($score) => strtoupper((string) $score) === 'X')->count();
            return $registration;
        })->sortByDesc('calculated_total');

        $event->load(['groups.scoringSessions.targets']);
        $currentSnapshots = EventRankingSnapshot::query()
            ->where('event_id', $event->id)
            ->where('status', 'locked')
            ->whereNull('superseded_at')
            ->get()
            ->keyBy('event_group_id');
        $requiresJudgeReview = $event->staff()->where('status', 'active')->whereIn('role', ['judge', 'chief_judge'])->exists();
        $groupStates = $event->groups->mapWithKeys(function (EventGroup $group) use ($registrations, $event, $requiresJudgeReview): array {
            $items = $registrations->where('event_group_id', $group->id)->values();
            $totalArrows = $group->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36);
            $arrowsPerEnd = $group->arrows_per_end ?: 6;
            $requiredEnds = (int) ceil($totalArrows / max(1, $arrowsPerEnd));
            $targets = $group->scoringSessions->flatMap->targets;
            $scoringTargets = $targets->where('status', '!=', 'dns');

            return [$group->id=>[
                'registrations'=>$items,
                'required_ends'=>$requiredEnds,
                'has_session'=>$group->scoringSessions->isNotEmpty(),
                'has_targets'=>$targets->isNotEmpty(),
                'unfinished_targets'=>$scoringTargets->where('status', '!=', 'completed')->count(),
                'requires_judge_review'=>$requiresJudgeReview,
                'unconfirmed_targets'=>$requiresJudgeReview ? $scoringTargets->where('judge_status', '!=', 'confirmed')->count() : 0,
                'incomplete_scores'=>$items->filter(fn ($item) => ! $item->score_verified_at && $item->scoreEntries->count() < $requiredEnds)->count(),
                'unverified'=>$items->whereNull('score_verified_at')->count(),
                'published'=>$items->isNotEmpty() && $items->every(fn ($item) => $item->result_published_at !== null),
            ]];
        });

        $canManageResults = request()->user()->can('manageScores', $event);
        $canApproveResults = request()->user()->can('approveResults', $event);
        $canCorrectScores = request()->user()->can('manageScoreCorrections', $event);

        return view('organizer.results.index', compact('event', 'registrations', 'groupStates', 'currentSnapshots', 'canManageResults', 'canApproveResults', 'canCorrectScores'));
    }

    public function edit(Event $event, EventRegistration $registration): View
    {
        $this->authorize('viewResults', $event);
        abort_unless($registration->event_id === $event->id, 404);

        $registration->load(['event_group', 'scoreEntries' => fn ($query) => $query->orderBy('end_number'), 'scoringAssignment.target']);
        $group = $registration->event_group;
        $totalArrows = $group?->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36);
        $arrowsPerEnd = $group?->arrows_per_end ?: 6;
        $requiredEnds = (int) ceil($totalArrows / max(1, $arrowsPerEnd));
        $scores = $registration->scoreEntries->flatMap(fn ($entry) => $entry->scores ?? []);
        $stats = [
            'total'=>$registration->scoreEntries->sum('end_total'),
            'ten_count'=>$scores->filter(fn ($score) => (string) $score === '10')->count(),
            'x_count'=>$scores->filter(fn ($score) => strtoupper((string) $score) === 'X')->count(),
        ];
        $correctionLogs = EventAuditLog::query()
            ->with('user')
            ->where('event_id', $event->id)
            ->where('action', 'results.score_corrected')
            ->where('subject_type', EventRegistration::class)
            ->where('subject_id', $registration->id)
            ->latest()
            ->get();
        $canCorrect = request()->user()->can('manageScoreCorrections', $event)
            && $registration->result_published_at === null
            && $registration->result_status !== 'dns'
            && $registration->status !== 'no_show';

        return view('organizer.results.edit', compact('event', 'registration', 'arrowsPerEnd', 'requiredEnds', 'stats', 'correctionLogs', 'canCorrect'));
    }

    public function update(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('manageScoreCorrections', $event);
        abort_unless($registration->event_id === $event->id, 404);
        abort_if($registration->result_published_at !== null, 422, '正式成績已發布，不能直接修改。');
        abort_if($registration->result_status === 'dns' || $registration->status === 'no_show', 422, 'DNS 選手不能輸入或修改分數。');

        $group = $registration->event_group;
        $totalArrows = $group?->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36);
        $arrowsPerEnd = $group?->arrows_per_end ?: 6;
        $requiredEnds = (int) ceil($totalArrows / max(1, $arrowsPerEnd));
        $validated = $request->validate([
            'ends'=>['nullable', 'array'],
            'ends.*'=>['nullable', 'array', 'max:'.$arrowsPerEnd],
            'ends.*.*'=>['nullable', 'string', 'regex:/^(X|10|[1-9]|M)$/i'],
            'correction_reason'=>['required', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($event, $registration, $validated, $request, $requiredEnds, $arrowsPerEnd): array {
            $locked = EventRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->result_published_at !== null, 422, '正式成績已發布，不能直接修改。');
            $snapshot = fn () => $locked->scoreEntries()->orderBy('end_number')->get()
                ->mapWithKeys(fn (EventScoreEntry $entry) => [(int) $entry->end_number => [
                    'scores'=>array_values($entry->scores ?? []),
                    'end_total'=>(int) $entry->end_total,
                ]])->all();
            $before = $snapshot();
            $oldTotal = collect($before)->sum('end_total');

            for ($end = 1; $end <= $requiredEnds; $end++) {
                $submitted = array_slice(array_values($validated['ends'][$end] ?? []), 0, $arrowsPerEnd);
                $hasScore = collect($submitted)->contains(fn ($score) => trim((string) $score) !== '');
                if (! $hasScore) {
                    $locked->scoreEntries()->where('end_number', $end)->delete();
                    continue;
                }

                $scores = collect(array_pad($submitted, $arrowsPerEnd, 'M'))
                    ->map(fn ($score) => trim((string) $score) === '' ? 'M' : strtoupper(trim((string) $score)))
                    ->sortByDesc(fn ($score) => $score === 'X' ? 11 : ($score === 'M' ? 0 : (int) $score))
                    ->values()
                    ->all();
                $total = collect($scores)->sum(fn ($score) => $score === 'X' ? 10 : ($score === 'M' ? 0 : (int) $score));
                EventScoreEntry::updateOrCreate(
                    ['event_registration_id'=>$locked->id, 'end_number'=>$end],
                    ['event_id'=>$event->id, 'user_id'=>$locked->user_id, 'scores'=>$scores, 'end_total'=>$total]
                );
            }

            $after = $snapshot();
            $newTotal = collect($after)->sum('end_total');
            $changes = collect(range(1, $requiredEnds))->map(function (int $end) use ($before, $after): ?array {
                $old = $before[$end] ?? null;
                $new = $after[$end] ?? null;
                if ($old === $new) return null;

                return ['end_number'=>$end, 'before'=>$old, 'after'=>$new];
            })->filter()->values()->all();

            if ($changes === []) {
                return ['changed'=>false, 'old_total'=>$oldTotal, 'new_total'=>$newTotal];
            }

            $entryCount = count($after);
            $locked->update([
                'score_submitted_at'=>$entryCount >= $requiredEnds ? now() : null,
                'score_verified_at'=>null,
                'score_verified_by'=>null,
                'result_status'=>null,
            ]);

            $target = $locked->scoringAssignment?->target;
            if ($target) {
                $target->update([
                    'judge_status'=>'pending',
                    'judge_note'=>null,
                    'reviewed_by'=>null,
                    'reviewed_at'=>null,
                    'confirmed_by'=>null,
                    'confirmed_at'=>null,
                ]);
            }

            EventAuditLog::create([
                'event_id'=>$event->id,
                'user_id'=>$request->user()->id,
                'action'=>'results.score_corrected',
                'subject_type'=>EventRegistration::class,
                'subject_id'=>$locked->id,
                'metadata'=>[
                    'athlete'=>$locked->name,
                    'old_total'=>$oldTotal,
                    'new_total'=>$newTotal,
                    'reason'=>$validated['correction_reason'],
                    'before'=>$before,
                    'after'=>$after,
                    'changes'=>$changes,
                ],
            ]);

            return ['changed'=>true, 'old_total'=>$oldTotal, 'new_total'=>$newTotal];
        });

        if (! $result['changed']) {
            return back()->with('success', '箭值沒有變更，未建立修正紀錄。');
        }

        return redirect()->route('organizer.events.results.index', $event)
            ->with('success', $registration->name.'的成績已由 '.$result['old_total'].' 分修正為 '.$result['new_total'].' 分，請重新進行裁判簽核與成績核准。');
    }

    public function verify(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('approveResults', $event);
        $validated = $request->validate(['registration_ids'=>['required','array','min:1'],'registration_ids.*'=>['integer']]);
        $items = $event->registrations()->with(['event_group','scoreEntries'])->whereIn('id',$validated['registration_ids'])->get();
        $dnsCount = 0;
        foreach ($items as $registration) {
            $didNotStart = $registration->status === 'no_show' || $registration->result_status === 'dns';
            if ($didNotStart) $dnsCount++;
            $registration->update([
                'score_verified_at'=>now(),
                'score_verified_by'=>$request->user()->id,
                'result_status'=>$didNotStart ? 'dns' : 'completed',
            ]);
        }
        EventAuditLog::create(['event_id'=>$event->id,'user_id'=>$request->user()->id,'action'=>'results.verified','metadata'=>['count'=>$items->count(),'dns_count'=>$dnsCount]]);
        return back()->with('success','已核准 '.$items->count().' 筆成績'.($dnsCount ? '，其中 '.$dnsCount.' 位標記為未出賽（DNS）' : '').'。');
    }

    public function verifyGroup(Request $request, Event $event, EventGroup $group): RedirectResponse
    {
        $this->authorize('approveResults', $event);
        abort_unless($group->event_id === $event->id, 404);

        $result = DB::transaction(function () use ($request, $event, $group): array {
            EventGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $registrations = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->whereIn('status', ['registered', 'checked_in', 'no_show'])
                ->with('scoreEntries')
                ->lockForUpdate()
                ->get();

            if ($registrations->isEmpty()) {
                return ['error'=>'此組別沒有可核准的選手。'];
            }

            $sessions = EventScoringSession::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->with('targets')
                ->get();
            $targets = $sessions->flatMap->targets;
            if ($sessions->isEmpty() || $targets->isEmpty()) {
                return ['error'=>'此組別尚未建立排靶與計分場次。'];
            }

            $unverified = $registrations->whereNull('score_verified_at');
            foreach ($unverified as $registration) {
                $didNotStart = $registration->status === 'no_show' || $registration->result_status === 'dns';
                $registration->update([
                    'score_verified_at'=>now(),
                    'score_verified_by'=>$request->user()->id,
                    'result_status'=>$didNotStart ? 'dns' : 'completed',
                ]);
            }

            EventAuditLog::create([
                'event_id'=>$event->id,
                'user_id'=>$request->user()->id,
                'action'=>'results.group_verified',
                'subject_type'=>EventGroup::class,
                'subject_id'=>$group->id,
                'metadata'=>['group_id'=>$group->id, 'count'=>$unverified->count()],
            ]);

            return ['count'=>$unverified->count()];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $group->name.'已核准 '.$result['count'].' 筆成績，可以進行正式發布。');
    }

    public function publish(
        Request $request,
        Event $event,
        EventGroup $group,
        EventBadgeAwardService $badges,
        QualificationRankingSnapshotService $rankingSnapshots
    ): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($group->event_id === $event->id, 404);

        $publication = DB::transaction(function () use ($event, $group, $request, $rankingSnapshots): array {
            EventGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $registrations = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->whereIn('status', ['registered', 'checked_in', 'no_show'])
                ->with('scoreEntries')
                ->lockForUpdate()
                ->get();

            abort_if($registrations->isEmpty(), 422, '此組別沒有可發布的選手。');

            $sessions = EventScoringSession::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->with('targets')
                ->get();
            abort_if($sessions->isEmpty(), 422, '此組別尚未建立排靶與計分場次。');
            $targets = $sessions->flatMap->targets;
            abort_if($targets->isEmpty(), 422, '此組別尚未建立任何靶位。');
            $requiresJudgeReview = $event->staff()->where('status', 'active')->whereIn('role', ['judge', 'chief_judge'])->exists();
            if ($requiresJudgeReview) {
                $unconfirmedTargets = $targets->where('status', '!=', 'dns')->where('judge_status', '!=', 'confirmed')->count();
                abort_if($unconfirmedTargets > 0, 422, '此組別還有 '.$unconfirmedTargets.' 個靶位尚未經主裁判簽核。');
            }
            $unverified = $registrations->whereNull('score_verified_at')->count();
            abort_if($unverified > 0, 422, '此組別還有 '.$unverified.' 位選手尚未經成績管理員或主裁判核准。');

            $now = now();
            $unpublished = $registrations->whereNull('result_published_at');
            foreach ($unpublished as $registration) {
                $registration->update(['result_published_at'=>$now]);
            }

            $group->qualificationPhase()->update([
                'status'=>'published',
                'locked_at'=>DB::raw('COALESCE(locked_at, CURRENT_TIMESTAMP)'),
                'completed_at'=>DB::raw('COALESCE(completed_at, CURRENT_TIMESTAMP)'),
                'published_at'=>$now,
            ]);

            $eventHasUnpublished = EventRegistration::query()
                ->where('event_id', $event->id)
                ->whereIn('status', ['registered', 'checked_in', 'no_show'])
                ->whereNull('result_published_at')
                ->exists();
            if (! $eventHasUnpublished) {
                $event->update(['completed_at'=>$now]);
            }

            EventAuditLog::create([
                'event_id'=>$event->id,
                'user_id'=>$request->user()->id,
                'action'=>'results.group_published',
                'subject_type'=>EventGroup::class,
                'subject_id'=>$group->id,
                'metadata'=>['group_id'=>$group->id, 'count'=>$unpublished->count()],
            ]);

            $snapshot = $rankingSnapshots->capture($event, $group, $request->user()->id);

            return ['published_count'=>$unpublished->count(), 'snapshot_version'=>$snapshot->version];
        });

        $awarded = $badges->awardPlacementsFor($event, $group->id);

        return back()->with('success', $group->name.'正式成績已發布（'.$publication['published_count'].' 人），排名種子快照 v'.$publication['snapshot_version'].' 已鎖定，已發放 '.$awarded.' 個名次 Badge。');
    }

    public function createRankingSnapshot(
        Request $request,
        Event $event,
        EventGroup $group,
        QualificationRankingSnapshotService $rankingSnapshots,
    ): RedirectResponse {
        $this->authorize('manageScores', $event);
        abort_unless($group->event_id === $event->id, 404);

        $snapshot = DB::transaction(function () use ($request, $event, $group, $rankingSnapshots) {
            EventGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $registrations = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->whereIn('status', ['registered', 'checked_in', 'no_show'])
                ->lockForUpdate()
                ->get();

            abort_if($registrations->isEmpty(), 422, '此組別沒有可建立快照的正式成績。');
            abort_if($registrations->contains(fn ($registration) => $registration->result_published_at === null), 422, '此組別並非所有成績都已正式發布。');

            $legacyVerified = 0;
            foreach ($registrations->whereNull('score_verified_at') as $registration) {
                $didNotStart = $registration->status === 'no_show' || $registration->result_status === 'dns';
                $registration->update([
                    'score_verified_at'=>$registration->result_published_at,
                    'score_verified_by'=>$request->user()->id,
                    'result_status'=>$didNotStart ? 'dns' : 'completed',
                ]);
                $legacyVerified++;
            }

            $publishedAt = $registrations->max('result_published_at') ?? now();
            $phase = $group->qualificationPhase()->lockForUpdate()->firstOrFail();
            $phase->update([
                'status'=>'published',
                'locked_at'=>$phase->locked_at ?? $publishedAt,
                'completed_at'=>$phase->completed_at ?? $publishedAt,
                'published_at'=>$phase->published_at ?? $publishedAt,
            ]);

            if ($legacyVerified > 0) {
                EventAuditLog::create([
                    'event_id'=>$event->id,
                    'user_id'=>$request->user()->id,
                    'action'=>'results.legacy_verification_backfilled',
                    'subject_type'=>EventGroup::class,
                    'subject_id'=>$group->id,
                    'metadata'=>['count'=>$legacyVerified, 'reason'=>'published_before_ranking_snapshots'],
                ]);
            }

            return $rankingSnapshots->capture($event, $group, $request->user()->id);
        });

        return back()->with('success', $group->name.'排名種子快照 v'.$snapshot->version.' 已補建完成，可以建立個人對抗表。');
    }
}
