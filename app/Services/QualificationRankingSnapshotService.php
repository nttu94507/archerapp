<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventGroup;
use App\Models\EventPhase;
use App\Models\EventRankingSnapshot;
use App\Models\EventRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QualificationRankingSnapshotService
{
    public const RANKING_RULE = [
        'order'=>['total_score:desc', 'ten_count:desc', 'x_count:desc'],
        'ties'=>'same rank; seed order requires resolution before bracket creation',
        'ineligible_statuses'=>['dns', 'dnf'],
    ];

    public function capture(Event $event, EventGroup $group, ?int $actorId = null): EventRankingSnapshot
    {
        return DB::transaction(function () use ($event, $group, $actorId): EventRankingSnapshot {
            /** @var EventPhase $phase */
            $phase = EventPhase::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->where('type', 'qualification')
                ->where('sequence', 1)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($phase->status === 'published' && $phase->published_at !== null, 422, '排名賽尚未正式發布，不能鎖定排名種子。');

            $rows = $this->rankingRows($event, $group);
            $sourceHash = hash('sha256', json_encode($rows->map(fn (array $row) => [
                $row['registration']->id, $row['registration']->result_status,
                $row['total_score'], $row['ten_count'], $row['x_count'], $row['is_eligible'],
            ])->values()->all(), JSON_THROW_ON_ERROR));

            $latest = EventRankingSnapshot::query()
                ->where('event_phase_id', $phase->id)
                ->latest('version')
                ->lockForUpdate()
                ->first();
            if ($latest && hash_equals($latest->source_hash, $sourceHash)) {
                return $latest;
            }

            $now = now();
            if ($latest && $latest->superseded_at === null) {
                $latest->update(['status'=>'superseded', 'superseded_at'=>$now]);
            }

            $snapshot = EventRankingSnapshot::create([
                'event_id'=>$event->id,
                'event_group_id'=>$group->id,
                'event_phase_id'=>$phase->id,
                'version'=>($latest?->version ?? 0) + 1,
                'status'=>'locked',
                'source_hash'=>$sourceHash,
                'ranking_rule'=>self::RANKING_RULE,
                'locked_at'=>$now,
                'created_by'=>$actorId,
            ]);

            foreach ($rows as $row) {
                /** @var EventRegistration $registration */
                $registration = $row['registration'];
                $snapshot->entries()->create([
                    'event_registration_id'=>$registration->id,
                    'user_id'=>$registration->user_id,
                    'rank_position'=>$row['rank_position'],
                    'seed_position'=>$row['seed_position'],
                    'total_score'=>$row['total_score'],
                    'ten_count'=>$row['ten_count'],
                    'x_count'=>$row['x_count'],
                    'result_status'=>$registration->result_status,
                    'is_eligible'=>$row['is_eligible'],
                    'tie_group'=>$row['tie_group'],
                    'requires_tiebreak'=>$row['requires_tiebreak'],
                    'athlete_name'=>$registration->name,
                    'team_name'=>$registration->team_name,
                ]);
            }

            EventAuditLog::create([
                'event_id'=>$event->id,
                'user_id'=>$actorId,
                'action'=>'ranking.snapshot_locked',
                'subject_type'=>EventRankingSnapshot::class,
                'subject_id'=>$snapshot->id,
                'metadata'=>[
                    'group_id'=>$group->id,
                    'phase_id'=>$phase->id,
                    'version'=>$snapshot->version,
                    'eligible'=>$rows->where('is_eligible', true)->count(),
                    'ties'=>$rows->where('requires_tiebreak', true)->count(),
                ],
            ]);

            return $snapshot->load('entries');
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function rankingRows(Event $event, EventGroup $group): Collection
    {
        $registrations = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('event_group_id', $group->id)
            ->whereIn('status', ['registered', 'checked_in', 'no_show'])
            ->with('scoreEntries')
            ->get();

        $rows = $registrations->map(function (EventRegistration $registration): array {
            $scores = $registration->scoreEntries->flatMap(fn ($entry) => $entry->scores ?? []);
            $eligible = in_array($registration->status, ['registered', 'checked_in'], true)
                && ! in_array($registration->result_status, ['dns', 'dnf'], true)
                && $registration->score_verified_at !== null
                && $registration->result_published_at !== null;

            return [
                'registration'=>$registration,
                'total_score'=>(int) $registration->scoreEntries->sum('end_total'),
                'ten_count'=>$scores->filter(fn ($score) => (string) $score === '10')->count(),
                'x_count'=>$scores->filter(fn ($score) => strtoupper((string) $score) === 'X')->count(),
                'is_eligible'=>$eligible,
                'rank_position'=>null,
                'seed_position'=>null,
                'tie_group'=>null,
                'requires_tiebreak'=>false,
            ];
        });

        $eligible = $rows->where('is_eligible', true)->sort(function (array $left, array $right): int {
            return [$right['total_score'], $right['ten_count'], $right['x_count'], -$right['registration']->id]
                <=> [$left['total_score'], $left['ten_count'], $left['x_count'], -$left['registration']->id];
        })->values();

        $tieGroups = $eligible->groupBy(fn (array $row) => $row['total_score'].'|'.$row['ten_count'].'|'.$row['x_count']);
        $tieNumber = 0;
        $previousSignature = null;
        $rank = 1;
        $ranked = $eligible->map(function (array $row, int $index) use ($tieGroups, &$tieNumber, &$previousSignature, &$rank): array {
            $signature = $row['total_score'].'|'.$row['ten_count'].'|'.$row['x_count'];
            if ($previousSignature !== null && $signature !== $previousSignature) {
                $rank = $index + 1;
            }
            $isTie = $tieGroups->get($signature)->count() > 1;
            if ($isTie && $signature !== $previousSignature) {
                $tieNumber++;
            }
            $row['rank_position'] = $rank;
            $row['seed_position'] = $index + 1;
            $row['tie_group'] = $isTie ? $tieNumber : null;
            $row['requires_tiebreak'] = $isTie;
            $previousSignature = $signature;
            return $row;
        });

        return $ranked->concat($rows->where('is_eligible', false)->sortBy(fn (array $row) => $row['registration']->id))->values();
    }
}
