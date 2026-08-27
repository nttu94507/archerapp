<?php

namespace App\Services;

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
}
