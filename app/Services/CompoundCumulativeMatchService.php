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
        $oneArrows = $this->normalizeArrows($oneArrows, 'participant_one_arrows');
        $twoArrows = $this->normalizeArrows($twoArrows, 'participant_two_arrows');

        return DB::transaction(function () use ($match, $oneArrows, $twoArrows, $actorId): EventEliminationMatch {
            $match = EventEliminationMatch::query()->with('bracket.event')->lockForUpdate()->findOrFail($match->id);
            if ($match->bracket->scoring_mode !== 'cumulative') {
                throw ValidationException::withMessages(['match'=>'此場比賽不是複合弓累計制。']);
            }
            if (! in_array($match->status, ['ready', 'in_progress'], true)) {
                throw ValidationException::withMessages(['match'=>'此場目前不能輸入累計分數。']);
            }
            if (! $match->participant_one_registration_id || ! $match->participant_two_registration_id) {
                throw ValidationException::withMessages(['match'=>'雙方選手尚未確定。']);
            }

            $endNumber = $match->ends()->count() + 1;
            if ($endNumber > 5) {
                throw ValidationException::withMessages(['match'=>'五趟已全部完成，請進行加射判定。']);
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

            $status = $endNumber < 5 ? 'in_progress' : ($oneTotal === $twoTotal ? 'awaiting_shoot_off' : 'completed');
            $winnerId = $status === 'completed'
                ? ($oneTotal > $twoTotal ? $match->participant_one_registration_id : $match->participant_two_registration_id)
                : null;
            $match->update([
                'participant_one_total'=>$oneTotal,
                'participant_two_total'=>$twoTotal,
                'current_end'=>min($endNumber + 1, 5),
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

    private function normalizeArrows(array $arrows, string $field): array
    {
        if (count($arrows) !== 3) {
            throw ValidationException::withMessages([$field=>'每位選手每趟必須輸入 3 箭。']);
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
