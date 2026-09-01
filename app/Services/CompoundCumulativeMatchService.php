<?php

namespace App\Services;

use App\Models\EventAuditLog;
use App\Models\EventEliminationMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompoundCumulativeMatchService
{
    public function __construct(private readonly EliminationMatchProgressionService $progression) {}

    public function recordEnd(EventEliminationMatch $match, array $oneArrows, array $twoArrows, ?int $actorId): EventEliminationMatch
    {
        $arrowCount = in_array($match->bracket->category, ['team','mixed_team'], true) ? (($match->bracket->category==='mixed_team') ? 4 : 6) : 3;
        $oneArrows = $this->normalizeArrows($oneArrows, 'participant_one_arrows', $arrowCount);
        $twoArrows = $this->normalizeArrows($twoArrows, 'participant_two_arrows', $arrowCount);

        return DB::transaction(function () use ($match, $oneArrows, $twoArrows, $actorId): EventEliminationMatch {
            $match = EventEliminationMatch::query()->with('bracket.event')->lockForUpdate()->findOrFail($match->id);
            if ($match->bracket->scoring_mode !== 'cumulative') {
                throw ValidationException::withMessages(['match'=>'此場比賽不是複合弓累計制。']);
            }
            if (! in_array($match->status, ['ready', 'in_progress'], true)) {
                throw ValidationException::withMessages(['match'=>'此場目前不能輸入累計分數。']);
            }
            $hasParticipants=in_array($match->bracket->category,['team','mixed_team'],true)
                ? ($match->participant_one_team_id && $match->participant_two_team_id)
                : ($match->participant_one_registration_id && $match->participant_two_registration_id);
            if (! $hasParticipants) {
                throw ValidationException::withMessages(['match'=>'雙方選手尚未確定。']);
            }

            $teamMatch=in_array($match->bracket->category,['team','mixed_team'],true);$maxEnds=$teamMatch?4:5;
            $participantOneId=$teamMatch?$match->participant_one_team_id:$match->participant_one_registration_id;$participantTwoId=$teamMatch?$match->participant_two_team_id:$match->participant_two_registration_id;
            $endNumber = $match->ends()->count() + 1;
            if ($endNumber > $maxEnds) {
                throw ValidationException::withMessages(['match'=>$maxEnds.'趟已全部完成，請進行加射判定。']);
            }
            $oneEndTotal = $this->total($oneArrows);
            $twoEndTotal = $this->total($twoArrows);
            $oneTotal = $match->participant_one_total + $oneEndTotal;
            $twoTotal = $match->participant_two_total + $twoEndTotal;

            $match->ends()->create([
                'end_number'=>$endNumber,
                'participant_one_arrows'=>$oneArrows,
                'participant_two_arrows'=>$twoArrows,
                'participant_one_end_total'=>$oneEndTotal,
                'participant_two_end_total'=>$twoEndTotal,
                'participant_one_running_total'=>$oneTotal,
                'participant_two_running_total'=>$twoTotal,
                'recorded_by'=>$actorId,
            ]);

            $status = $endNumber < $maxEnds ? 'in_progress' : ($oneTotal === $twoTotal ? 'awaiting_shoot_off' : 'completed');
            $winnerId = $status === 'completed'
                ? ($oneTotal > $twoTotal ? $participantOneId : $participantTwoId)
                : null;
            $match->update([
                'participant_one_total'=>$oneTotal,
                'participant_two_total'=>$twoTotal,
                'current_end'=>min($endNumber + 1, $maxEnds),
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
                'action'=>'elimination.end_recorded',
                'subject_type'=>EventEliminationMatch::class,
                'subject_id'=>$match->id,
                'metadata'=>[
                    'end_number'=>$endNumber,
                    'participant_one_arrows'=>$oneArrows,
                    'participant_two_arrows'=>$twoArrows,
                    'end_totals'=>[$oneEndTotal, $twoEndTotal],
                    'running_totals'=>[$oneTotal, $twoTotal],
                    'status'=>$status,
                ],
            ]);

            return $match->fresh(['ends', 'participantOneEntry', 'participantTwoEntry', 'bracket']);
        });
    }

    private function normalizeArrows(array $arrows, string $field, int $required=3): array
    {
        if (count($arrows) !== $required) {
            throw ValidationException::withMessages([$field=>'每隊每趟必須輸入 '.$required.' 箭。']);
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
