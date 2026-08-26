<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventGroup;
use App\Models\EventRegistration;
use App\Models\EventScoreEntry;
use App\Models\EventScoringSession;
use App\Services\EventBadgeAwardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventResultController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('manageScores', $event);
        $registrations = $event->registrations()->with(['event_group', 'scoreEntries'])->whereIn('status', ['registered','checked_in'])->get()->map(function ($registration) {
            $registration->calculated_total = $registration->scoreEntries->sum('end_total');
            $scores = $registration->scoreEntries->flatMap(fn ($entry) => $entry->scores ?? []);
            $registration->calculated_ten_count = $scores->filter(fn ($score) => (string) $score === '10')->count();
            $registration->calculated_x_count = $scores->filter(fn ($score) => strtoupper((string) $score) === 'X')->count();
            return $registration;
        })->sortByDesc('calculated_total');

        $event->load(['groups.scoringSessions.targets']);
        $requiresJudgeReview = $event->staff()->where('status', 'active')->whereIn('role', ['judge', 'chief_judge'])->exists();
        $groupStates = $event->groups->mapWithKeys(function (EventGroup $group) use ($registrations, $event, $requiresJudgeReview): array {
            $items = $registrations->where('event_group_id', $group->id)->values();
            $totalArrows = $group->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36);
            $arrowsPerEnd = $group->arrows_per_end ?: 6;
            $requiredEnds = (int) ceil($totalArrows / max(1, $arrowsPerEnd));
            $targets = $group->scoringSessions->flatMap->targets;

            return [$group->id=>[
                'registrations'=>$items,
                'required_ends'=>$requiredEnds,
                'has_session'=>$group->scoringSessions->isNotEmpty(),
                'has_targets'=>$targets->isNotEmpty(),
                'unfinished_targets'=>$targets->where('status', '!=', 'completed')->count(),
                'requires_judge_review'=>$requiresJudgeReview,
                'unconfirmed_targets'=>$requiresJudgeReview ? $targets->where('judge_status', '!=', 'confirmed')->count() : 0,
                'incomplete_scores'=>$items->filter(fn ($item) => ! $item->score_verified_at && $item->scoreEntries->count() < $requiredEnds)->count(),
                'unverified'=>$items->whereNull('score_verified_at')->count(),
                'published'=>$items->isNotEmpty() && $items->every(fn ($item) => $item->result_published_at !== null),
            ]];
        });

        return view('organizer.results.index', compact('event', 'registrations', 'groupStates'));
    }

    public function edit(Event $event, EventRegistration $registration): View
    {
        $this->authorize('manageScores', $event);
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

        return view('organizer.results.edit', compact('event', 'registration', 'arrowsPerEnd', 'requiredEnds', 'stats'));
    }

    public function update(Request $request, Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($registration->event_id === $event->id, 404);
        abort_if($registration->result_published_at !== null, 422, '正式成績已發布，不能直接修改。');

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
            $oldTotal = $locked->scoreEntries()->sum('end_total');

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

            $entryCount = $locked->scoreEntries()->count();
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

            $newTotal = $locked->scoreEntries()->sum('end_total');
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
                ],
            ]);

            return ['old_total'=>$oldTotal, 'new_total'=>$newTotal];
        });

        return redirect()->route('organizer.events.results.index', $event)
            ->with('success', $registration->name.'的成績已由 '.$result['old_total'].' 分修正為 '.$result['new_total'].' 分，請重新進行裁判及主辦確認。');
    }

    public function verify(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        $validated = $request->validate(['registration_ids'=>['required','array','min:1'],'registration_ids.*'=>['integer']]);
        $items = $event->registrations()->with(['event_group','scoreEntries'])->whereIn('id',$validated['registration_ids'])->get();
        $dnfCount = 0;
        foreach ($items as $registration) {
            $didNotStart = $registration->status !== 'checked_in' && $registration->checked_in_at === null;
            if ($didNotStart) $dnfCount++;
            $registration->update([
                'score_verified_at'=>now(),
                'score_verified_by'=>$request->user()->id,
                'result_status'=>$didNotStart ? 'dnf' : 'completed',
            ]);
        }
        EventAuditLog::create(['event_id'=>$event->id,'user_id'=>$request->user()->id,'action'=>'results.verified','metadata'=>['count'=>$items->count(),'dnf_count'=>$dnfCount]]);
        return back()->with('success','已確認 '.$items->count().' 筆成績'.($dnfCount ? '，其中 '.$dnfCount.' 位標記為棄賽（DNF）' : '').'。');
    }

    public function verifyGroup(Request $request, Event $event, EventGroup $group): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($group->event_id === $event->id, 404);

        $result = DB::transaction(function () use ($request, $event, $group): array {
            EventGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $registrations = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->whereIn('status', ['registered', 'checked_in'])
                ->with('scoreEntries')
                ->lockForUpdate()
                ->get();

            if ($registrations->isEmpty()) {
                return ['error'=>'此組別沒有可確認的選手。'];
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
                $didNotStart = $registration->status !== 'checked_in' && $registration->checked_in_at === null;
                $registration->update([
                    'score_verified_at'=>now(),
                    'score_verified_by'=>$request->user()->id,
                    'result_status'=>$didNotStart ? 'dnf' : 'completed',
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

        return back()->with('success', $group->name.'已確認 '.$result['count'].' 筆成績，可以進行正式發布。');
    }

    public function publish(Request $request, Event $event, EventGroup $group, EventBadgeAwardService $badges): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        abort_unless($group->event_id === $event->id, 404);

        $publishedCount = DB::transaction(function () use ($event, $group, $request): int {
            EventGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $registrations = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->whereIn('status', ['registered', 'checked_in'])
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
                $unconfirmedTargets = $targets->where('judge_status', '!=', 'confirmed')->count();
                abort_if($unconfirmedTargets > 0, 422, '此組別還有 '.$unconfirmedTargets.' 個靶位尚未經主裁判簽核。');
            }
            $unverified = $registrations->whereNull('score_verified_at')->count();
            abort_if($unverified > 0, 422, '此組別還有 '.$unverified.' 位選手尚未經主辦方確認。');

            $now = now();
            $unpublished = $registrations->whereNull('result_published_at');
            foreach ($unpublished as $registration) {
                $registration->update(['result_published_at'=>$now]);
            }

            $eventHasUnpublished = EventRegistration::query()
                ->where('event_id', $event->id)
                ->whereIn('status', ['registered', 'checked_in'])
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

            return $unpublished->count();
        });

        $awarded = $badges->awardPlacementsFor($event, $group->id);

        return back()->with('success', $group->name.'正式成績已發布（'.$publishedCount.' 人），已發放 '.$awarded.' 個名次 Badge。');
    }
}
