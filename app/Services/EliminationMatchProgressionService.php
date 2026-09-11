<?php

namespace App\Services;

use App\Models\EventEliminationMatch;
use App\Models\EventRankingSnapshotEntry;

class EliminationMatchProgressionService
{
    public function advance(EventEliminationMatch $match, int $winnerId): void
    {
        if (in_array($match->bracket->category, ['team','mixed_team'], true)) {
            $this->advanceTeam($match, $winnerId);
            return;
        }
        $winnerIsOne = $winnerId === $match->participant_one_registration_id;
        $winnerEntry = $winnerIsOne ? $match->participantOneEntry : $match->participantTwoEntry;
        $loserEntry = $winnerIsOne ? $match->participantTwoEntry : $match->participantOneEntry;

        $this->placeParticipant($match->nextMatch, $match->next_slot, $winnerEntry);
        $this->placeParticipant($match->loserNextMatch, $match->loser_next_slot, $loserEntry);
    }

    private function advanceTeam(EventEliminationMatch $match, int $winnerId): void
    {
        $winnerIsOne=$winnerId===$match->participant_one_team_id;
        $this->placeTeam($match->nextMatch,$match->next_slot,$winnerId,$winnerIsOne?$match->participant_one_seed:$match->participant_two_seed);
        $loserId=$winnerIsOne?$match->participant_two_team_id:$match->participant_one_team_id;
        $this->placeTeam($match->loserNextMatch,$match->loser_next_slot,$loserId,$winnerIsOne?$match->participant_two_seed:$match->participant_one_seed);
    }

    private function placeTeam(?EventEliminationMatch $destination, ?int $slot, ?int $teamId, ?int $seed): void
    {
        if(!$destination||!$slot||!$teamId)return;$word=$slot===1?'one':'two';
        $destination->update(["participant_{$word}_team_id"=>$teamId,"participant_{$word}_seed"=>$seed]);$destination->refresh();
        if($destination->participant_one_team_id&&$destination->participant_two_team_id)$destination->update(['status'=>'ready']);
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
