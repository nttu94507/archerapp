<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventEliminationBracket;
use App\Models\EventEliminationMatch;
use App\Models\EventGroup;
use App\Models\EventPhase;
use App\Models\EventRankingSnapshot;
use App\Models\EventRankingSnapshotEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IndividualEliminationBracketService
{
    public const SIZES = [4, 8, 16, 32, 64];

    public function create(
        Event $event,
        EventGroup $group,
        int $bracketSize,
        bool $bronzeMatchEnabled = true,
        ?int $actorId = null,
    ): EventEliminationBracket {
        if (! in_array($bracketSize, self::SIZES, true)) {
            throw ValidationException::withMessages(['bracket_size'=>'對抗表只支援 4、8、16、32 或 64 人。']);
        }
        if ($group->event_id !== $event->id) {
            throw ValidationException::withMessages(['event_group_id'=>'所選組別不屬於此賽事。']);
        }
        if (! $event->hasPlanFeature('individual_elimination')) {
            throw ValidationException::withMessages(['plan'=>'免費方案僅提供排名賽，個人對抗賽需使用付費方案。']);
        }

        return DB::transaction(function () use ($event, $group, $bracketSize, $bronzeMatchEnabled, $actorId): EventEliminationBracket {
            $snapshot = EventRankingSnapshot::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->where('status', 'locked')
                ->whereNull('superseded_at')
                ->with(['entries'=>fn ($query) => $query->where('is_eligible', true)->orderBy('seed_position')])
                ->lockForUpdate()
                ->first();

            if (! $snapshot) {
                throw ValidationException::withMessages(['event_group_id'=>'此組別尚未正式發布排名，沒有可用的種子快照。']);
            }
            if (EventEliminationBracket::where('event_group_id', $group->id)->exists()) {
                throw ValidationException::withMessages(['event_group_id'=>'此組別已建立個人對抗表，不能重複生成。']);
            }

            $entrants = $snapshot->entries->take($bracketSize)->values();
            if ($entrants->count() < 2) {
                throw ValidationException::withMessages(['event_group_id'=>'至少需要 2 名具備有效排名的選手才能建立對抗表。']);
            }
            if ($entrants->contains('requires_tiebreak', true)) {
                throw ValidationException::withMessages(['event_group_id'=>'種子範圍內仍有同分選手，請先完成加射或主裁判判定。']);
            }

            $now = now();
            $scoringMode = $group->bow_type === 'compound' ? 'cumulative' : 'set';
            $sequence = ((int) EventPhase::where('event_group_id', $group->id)->max('sequence')) + 1;
            $phase = EventPhase::create([
                'event_id'=>$event->id,
                'event_group_id'=>$group->id,
                'name'=>$group->name.' 個人對抗賽',
                'type'=>'elimination',
                'sequence'=>$sequence,
                'scoring_mode'=>$scoringMode,
                'status'=>'ready',
                'settings'=>[
                    'category'=>'individual',
                    'bracket_size'=>$bracketSize,
                    'ranking_snapshot_uuid'=>$snapshot->uuid,
                    'bronze_match_enabled'=>$bronzeMatchEnabled,
                ],
                'locked_at'=>$now,
                'created_by'=>$actorId,
            ]);

            $bracket = EventEliminationBracket::create([
                'event_id'=>$event->id,
                'event_group_id'=>$group->id,
                'event_phase_id'=>$phase->id,
                'event_ranking_snapshot_id'=>$snapshot->id,
                'name'=>$group->name.' 個人對抗表',
                'scoring_mode'=>$scoringMode,
                'bracket_size'=>$bracketSize,
                'status'=>'ready',
                'bronze_match_enabled'=>$bronzeMatchEnabled,
                'locked_at'=>$now,
                'created_by'=>$actorId,
            ]);

            $this->buildMatches($bracket, $entrants->keyBy('seed_position'), $bronzeMatchEnabled);
            $phase->update(['settings'=>array_merge($phase->settings, ['bracket_uuid'=>$bracket->uuid])]);

            EventAuditLog::create([
                'event_id'=>$event->id,
                'user_id'=>$actorId,
                'action'=>'elimination.bracket_created',
                'subject_type'=>EventEliminationBracket::class,
                'subject_id'=>$bracket->id,
                'metadata'=>[
                    'group_id'=>$group->id,
                    'ranking_snapshot_id'=>$snapshot->id,
                    'bracket_size'=>$bracketSize,
                    'entrants'=>$entrants->count(),
                    'byes'=>$bracketSize - $entrants->count(),
                    'scoring_mode'=>$scoringMode,
                    'bronze_match_enabled'=>$bronzeMatchEnabled,
                ],
            ]);

            return $bracket->load(['group', 'rankingSnapshot', 'matches.participantOneEntry', 'matches.participantTwoEntry']);
        });
    }

    private function buildMatches(EventEliminationBracket $bracket, $entrantsBySeed, bool $bronzeMatchEnabled): void
    {
        $roundCount = (int) log($bracket->bracket_size, 2);
        $rounds = [];
        for ($round = 1; $round <= $roundCount; $round++) {
            $matchCount = (int) ($bracket->bracket_size / (2 ** $round));
            for ($position = 1; $position <= $matchCount; $position++) {
                $rounds[$round][$position] = $bracket->matches()->create([
                    'round_number'=>$round,
                    'position'=>$position,
                    'match_type'=>'main',
                    'label'=>$this->roundLabel($matchCount),
                    'status'=>'pending',
                ]);
            }
        }

        $bronze = null;
        if ($bronzeMatchEnabled && $roundCount >= 2) {
            $bronze = $bracket->matches()->create([
                'round_number'=>$roundCount,
                'position'=>1,
                'match_type'=>'bronze',
                'label'=>'季軍賽',
                'status'=>'pending',
            ]);
        }

        for ($round = 1; $round < $roundCount; $round++) {
            foreach ($rounds[$round] as $position => $match) {
                $nextPosition = (int) ceil($position / 2);
                $match->update([
                    'next_match_id'=>$rounds[$round + 1][$nextPosition]->id,
                    'next_slot'=>$position % 2 === 1 ? 1 : 2,
                    'loser_next_match_id'=>($bronze && $round === $roundCount - 1) ? $bronze->id : null,
                    'loser_next_slot'=>($bronze && $round === $roundCount - 1) ? $position : null,
                ]);
            }
        }

        $seedOrder = $this->seedOrder($bracket->bracket_size);
        foreach ($rounds[1] as $position => $match) {
            $one = $entrantsBySeed->get($seedOrder[($position - 1) * 2]);
            $two = $entrantsBySeed->get($seedOrder[(($position - 1) * 2) + 1]);
            $match->update([
                'participant_one_snapshot_entry_id'=>$one?->id,
                'participant_two_snapshot_entry_id'=>$two?->id,
                'participant_one_registration_id'=>$one?->event_registration_id,
                'participant_two_registration_id'=>$two?->event_registration_id,
                'participant_one_seed'=>$one?->seed_position,
                'participant_two_seed'=>$two?->seed_position,
                'status'=>$one && $two ? 'ready' : (($one || $two) ? 'walkover' : 'pending'),
                'winner_registration_id'=>($one && ! $two) ? $one->event_registration_id : ((! $one && $two) ? $two->event_registration_id : null),
                'completed_at'=>($one xor $two) ? now() : null,
            ]);

            if ($one xor $two) {
                $winner = $one ?: $two;
                $next = $match->nextMatch;
                if ($next) {
                    $slot = $match->next_slot === 1 ? 'one' : 'two';
                    $next->update([
                        "participant_{$slot}_snapshot_entry_id"=>$winner->id,
                        "participant_{$slot}_registration_id"=>$winner->event_registration_id,
                        "participant_{$slot}_seed"=>$winner->seed_position,
                    ]);
                }
            }
        }

        foreach ($rounds as $round => $matches) {
            if ($round === 1) continue;
            foreach ($matches as $match) {
                if ($match->participant_one_registration_id && $match->participant_two_registration_id) {
                    $match->update(['status'=>'ready']);
                }
            }
        }
    }

    /** @return array<int, int> */
    private function seedOrder(int $size): array
    {
        $seeds = [1, 2];
        while (count($seeds) < $size) {
            $sum = count($seeds) * 2 + 1;
            $next = [];
            foreach ($seeds as $index => $seed) {
                if ($index % 2 === 0) {
                    array_push($next, $seed, $sum - $seed);
                } else {
                    array_push($next, $sum - $seed, $seed);
                }
            }
            $seeds = $next;
        }
        return $seeds;
    }

    private function roundLabel(int $matchCount): string
    {
        return match ($matchCount) {
            1=>'決賽', 2=>'準決賽', 4=>'八強賽', 8=>'十六強賽',
            16=>'三十二強賽', 32=>'六十四強賽', default=>'淘汰賽',
        };
    }
}
