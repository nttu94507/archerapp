<?php

namespace App\Services;

use App\Models\EventAuditLog;
use App\Models\EventEliminationMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurveSetMatchService
{
    public function __construct(private readonly EliminationMatchProgressionService $progression) {}

    public function recordSet(EventEliminationMatch $match, array $oneArrows, array $twoArrows, ?int $actorId): EventEliminationMatch
    {
        $arrowCount = in_array($match->bracket->category, ['team','mixed_team'], true) ? (($match->bracket->category==='mixed_team') ? 4 : 6) : 3;
        $oneArrows = $this->normalizeArrows($oneArrows, 'participant_one_arrows', $arrowCount);
        $twoArrows = $this->normalizeArrows($twoArrows, 'participant_two_arrows', $arrowCount);

        return DB::transaction(function () use ($match, $oneArrows, $twoArrows, $actorId): EventEliminationMatch {
            $match = EventEliminationMatch::query()->with('bracket.event')->lockForUpdate()->findOrFail($match->id);
            if ($match->bracket->scoring_mode !== 'set') {
                throw ValidationException::withMessages(['match'=>'此場比賽不是局分制。']);
            }
            if (! in_array($match->status, ['ready', 'in_progress'], true)) {
                throw ValidationException::withMessages(['match'=>'此場目前不能輸入局分。']);
            }
            $hasParticipants=in_array($match->bracket->category,['team','mixed_team'],true)
                ? ($match->participant_one_team_id && $match->participant_two_team_id)
                : ($match->participant_one_registration_id && $match->participant_two_registration_id);
            if (! $hasParticipants) {
                throw ValidationException::withMessages(['match'=>'雙方選手尚未確定。']);
            }

            $teamMatch=in_array($match->bracket->category,['team','mixed_team'],true);$maxSets=$teamMatch?4:5;$winPoints=$teamMatch?5:6;
            $participantOneId=$teamMatch?$match->participant_one_team_id:$match->participant_one_registration_id;$participantTwoId=$teamMatch?$match->participant_two_team_id:$match->participant_two_registration_id;
            $setNumber = $match->sets()->count() + 1;
            if ($setNumber > $maxSets) {
                throw ValidationException::withMessages(['match'=>$maxSets.'局已全部完成，請進行加射判定。']);
            }
            $oneTotal = $this->total($oneArrows);
            $twoTotal = $this->total($twoArrows);
            [$onePoints, $twoPoints] = $oneTotal === $twoTotal ? [1, 1] : ($oneTotal > $twoTotal ? [2, 0] : [0, 2]);

            $match->sets()->create([
                'set_number'=>$setNumber,
                'participant_one_arrows'=>$oneArrows,
                'participant_two_arrows'=>$twoArrows,
                'participant_one_total'=>$oneTotal,
                'participant_two_total'=>$twoTotal,
                'participant_one_set_points'=>$onePoints,
                'participant_two_set_points'=>$twoPoints,
                'recorded_by'=>$actorId,
            ]);

            $oneScore = $match->participant_one_set_points + $onePoints;
            $twoScore = $match->participant_two_set_points + $twoPoints;
            $status = 'in_progress';
            $winnerId = null;
            if ($oneScore >= $winPoints || $twoScore >= $winPoints || ($setNumber === $maxSets && $oneScore !== $twoScore)) {
                $winnerId = $oneScore > $twoScore ? $participantOneId : $participantTwoId;
                $status = 'completed';
            } elseif ($setNumber === $maxSets) {
                $status = 'awaiting_shoot_off';
            }

            $match->update([
                'participant_one_set_points'=>$oneScore,
                'participant_two_set_points'=>$twoScore,
                'current_set'=>min($setNumber + 1, $maxSets),
                'status'=>$status,
                'winner_registration_id'=>$teamMatch?null:$winnerId,
                'loser_registration_id'=>$teamMatch?null:($winnerId ? ($winnerId === $participantOneId ? $participantTwoId : $participantOneId) : null),
                'winner_team_id'=>$teamMatch?$winnerId:null,
                'loser_team_id'=>$teamMatch&&$winnerId?($winnerId===$participantOneId?$participantTwoId:$participantOneId):null,
                'completed_at'=>$winnerId ? now() : null,
            ]);

            if ($winnerId) {
                $this->progression->advance($match->fresh(), $winnerId);
            }

            EventAuditLog::create([
                'event_id'=>$match->bracket->event_id,
                'user_id'=>$actorId,
                'action'=>'elimination.set_recorded',
                'subject_type'=>EventEliminationMatch::class,
                'subject_id'=>$match->id,
                'metadata'=>[
                    'set_number'=>$setNumber,
                    'participant_one_arrows'=>$oneArrows,
                    'participant_two_arrows'=>$twoArrows,
                    'participant_one_total'=>$oneTotal,
                    'participant_two_total'=>$twoTotal,
                    'set_points'=>[$onePoints, $twoPoints],
                    'match_points'=>[$oneScore, $twoScore],
                    'status'=>$status,
                ],
            ]);

            return $match->fresh(['sets', 'participantOneEntry', 'participantTwoEntry', 'bracket']);
        });
    }

    private function normalizeArrows(array $arrows, string $field, int $required=3): array
    {
        if (count($arrows) !== $required) {
            throw ValidationException::withMessages([$field=>'每隊每局必須輸入 '.$required.' 箭。']);
        }
        return collect($arrows)->map(function ($arrow) use ($field): string {
            $value = strtoupper(trim((string) $arrow));
            if (! in_array($value, ['X', '10', '9', '8', '7', '6', '5', '4', '3', '2', '1', 'M'], true)) {
                throw ValidationException::withMessages([$field=>'箭值只能是 X、10～1 或 M。']);
            }
            return $value;
        })->all();
    }

    private function total(array $arrows): int
    {
        return collect($arrows)->sum(fn (string $arrow) => $arrow === 'X' ? 10 : ($arrow === 'M' ? 0 : (int) $arrow));
    }
}
