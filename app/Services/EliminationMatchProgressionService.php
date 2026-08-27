<?php

namespace App\Services;

use App\Models\EventAuditLog;
use App\Models\EventEliminationMatch;
use App\Models\EventRankingSnapshotEntry;

class EliminationMatchProgressionService
{
    public function advance(EventEliminationMatch $match, int $winnerId): void
    {
        $winnerIsOne = $winnerId === $match->participant_one_registration_id;
        $winnerEntry = $winnerIsOne ? $match->participantOneEntry : $match->participantTwoEntry;
        $loserEntry = $winnerIsOne ? $match->participantTwoEntry : $match->participantOneEntry;

        $this->placeParticipant($match->nextMatch, $match->next_slot, $winnerEntry);
        $this->placeParticipant($match->loserNextMatch, $match->loser_next_slot, $loserEntry);
        $this->reconcileBronzeWalkover($match->loserNextMatch);
    }

    private function placeParticipant(?EventEliminationMatch $destination, ?int $slot, ?EventRankingSnapshotEntry $entry): void
    {
        if (! $destination || ! $slot || ! $entry) return;
        $word = $slot === 1 ? 'one' : 'two';
        $destination->update([
            "participant_{$word}_snapshot_entry_id"=>$entry->id,
            "participant_{$word}_registration_id"=>$entry->event_registration_id,
            "participant_{$word}_seed"=>$entry->seed_position,
        ]);
        $destination->refresh();
        if ($destination->participant_one_registration_id && $destination->participant_two_registration_id) {
            $destination->update(['status'=>'ready']);
        }
    }

    public function reconcileBronzeWalkover(?EventEliminationMatch $bronze): bool
    {
        if (! $bronze || $bronze->match_type !== 'bronze') return false;

        $bronze->refresh();
        if ($bronze->winner_registration_id || $bronze->status === 'completed') return false;

        $feeders = EventEliminationMatch::query()
            ->where('loser_next_match_id', $bronze->id)
            ->get();
        if ($feeders->isEmpty() || $feeders->contains(
            fn (EventEliminationMatch $match) => ! in_array($match->status, ['completed', 'walkover'], true)
        )) return false;

        $participants = collect([
            $bronze->participant_one_registration_id,
            $bronze->participant_two_registration_id,
        ])->filter()->unique()->values();
        if ($participants->count() !== 1) return false;

        $winnerId = (int) $participants->first();
        $bronze->update([
            'status'=>'walkover',
            'winner_registration_id'=>$winnerId,
            'loser_registration_id'=>null,
            'completed_at'=>now(),
        ]);

        EventAuditLog::create([
            'event_id'=>$bronze->bracket->event_id,
            'action'=>'elimination.bronze_walkover_completed',
            'subject_type'=>EventEliminationMatch::class,
            'subject_id'=>$bronze->id,
            'metadata'=>[
                'winner_registration_id'=>$winnerId,
                'reason'=>'only_eligible_semifinal_loser',
            ],
        ]);

        return true;
    }
}
