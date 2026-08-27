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
        $oneArrows = $this->normalizeArrows($oneArrows, 'participant_one_arrows');
        $twoArrows = $this->normalizeArrows($twoArrows, 'participant_two_arrows');

        return DB::transaction(function () use ($match, $oneArrows, $twoArrows, $actorId): EventEliminationMatch {
            $match = EventEliminationMatch::query()->with('bracket.event')->lockForUpdate()->findOrFail($match->id);
            if ($match->bracket->scoring_mode !== 'set') {
                throw ValidationException::withMessages(['match'=>'此場比賽不是局分制。']);
            }
            if (! in_array($match->status, ['ready', 'in_progress'], true)) {
                throw ValidationException::withMessages(['match'=>'此場目前不能輸入局分。']);
            }
            if (! $match->participant_one_registration_id || ! $match->participant_two_registration_id) {
                throw ValidationException::withMessages(['match'=>'雙方選手尚未確定。']);
            }

            $setNumber = $match->sets()->count() + 1;
            if ($setNumber > 5) {
                throw ValidationException::withMessages(['match'=>'五局已全部完成，請進行加射判定。']);
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
            if ($oneScore >= 6 || $twoScore >= 6 || ($setNumber === 5 && $oneScore !== $twoScore)) {
                $winnerId = $oneScore > $twoScore ? $match->participant_one_registration_id : $match->participant_two_registration_id;
                $status = 'completed';
            } elseif ($setNumber === 5) {
                $status = 'awaiting_shoot_off';
            }

            $match->update([
                'participant_one_set_points'=>$oneScore,
                'participant_two_set_points'=>$twoScore,
                'current_set'=>min($setNumber + 1, 5),
                'status'=>$status,
                'winner_registration_id'=>$winnerId,
                'loser_registration_id'=>$winnerId ? ($winnerId === $match->participant_one_registration_id ? $match->participant_two_registration_id : $match->participant_one_registration_id) : null,
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

    private function normalizeArrows(array $arrows, string $field): array
    {
        if (count($arrows) !== 3) {
            throw ValidationException::withMessages([$field=>'每位選手每局必須輸入 3 箭。']);
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
