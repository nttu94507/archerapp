<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventGroup;
use App\Models\EventRegistration;
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
            return $registration;
        })->sortByDesc('calculated_total');

        $event->load(['groups.scoringSessions.targets']);
        $groupStates = $event->groups->mapWithKeys(function (EventGroup $group) use ($registrations, $event): array {
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
                'incomplete_scores'=>$items->filter(fn ($item) => $item->result_status !== 'dnf' && (! $item->score_submitted_at || $item->scoreEntries->count() < $requiredEnds))->count(),
                'unverified'=>$items->whereNull('score_verified_at')->count(),
                'published'=>$items->isNotEmpty() && $items->every(fn ($item) => $item->result_published_at !== null),
            ]];
        });

        return view('organizer.results.index', compact('event', 'registrations', 'groupStates'));
    }

    public function verify(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageScores', $event);
        $validated = $request->validate(['registration_ids'=>['required','array','min:1'],'registration_ids.*'=>['integer']]);
        $items = $event->registrations()->with(['event_group','scoreEntries'])->whereIn('id',$validated['registration_ids'])->get();
        $dnfCount = 0;
        foreach ($items as $registration) {
            $totalArrows = $registration->event_group?->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36);
            $requiredEnds = (int) ceil($totalArrows / max(1, $registration->event_group?->arrows_per_end ?: 6));
            $completed = $registration->score_submitted_at && $registration->scoreEntries->count() >= $requiredEnds;
            if (! $completed) $dnfCount++;
            $registration->update([
                'score_verified_at'=>now(),
                'score_verified_by'=>$request->user()->id,
                'result_status'=>$completed ? 'completed' : 'dnf',
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

            $unfinishedTargets = $targets->where('status', '!=', 'completed')->count();
            if ($unfinishedTargets > 0) {
                return ['error'=>'此組別還有 '.$unfinishedTargets.' 個靶位尚未完成。'];
            }

            $totalArrows = $group->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36);
            $requiredEnds = (int) ceil($totalArrows / max(1, $group->arrows_per_end ?: 6));
            $incomplete = $registrations->filter(fn ($registration) => $registration->result_status !== 'dnf' && (! $registration->score_submitted_at || $registration->scoreEntries->count() < $requiredEnds))->count();
            if ($incomplete > 0) {
                return ['error'=>'此組別還有 '.$incomplete.' 位選手成績不完整。'];
            }

            $unverified = $registrations->whereNull('score_verified_at');
            foreach ($unverified as $registration) {
                $registration->update(['score_verified_at'=>now(), 'score_verified_by'=>$request->user()->id, 'result_status'=>'completed']);
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
            $unfinishedTargets = $targets->where('status', '!=', 'completed')->count();
            abort_if($unfinishedTargets > 0, 422, '此組別還有 '.$unfinishedTargets.' 個靶位尚未完成。');

            $totalArrows = $group->arrow_count ?: ($event->mode === 'indoor' ? 30 : 36);
            $requiredEnds = (int) ceil($totalArrows / max(1, $group->arrows_per_end ?: 6));
            $incomplete = $registrations->filter(fn ($registration) => $registration->result_status !== 'dnf' && (! $registration->score_submitted_at || $registration->scoreEntries->count() < $requiredEnds))->count();
            abort_if($incomplete > 0, 422, '此組別還有 '.$incomplete.' 位選手成績不完整。');

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
