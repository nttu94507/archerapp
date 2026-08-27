<?php

namespace App\Services;

use App\Models\EventAuditLog;
use App\Models\EventEliminationMatch;
use App\Models\EventEliminationShootOff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EliminationShootOffService
{
    public function __construct(private readonly EliminationMatchProgressionService $progression) {}

    public function record(EventEliminationMatch $match, string $oneArrow, string $twoArrow, ?int $actorId): EventEliminationMatch
    {
        $oneArrow = $this->normalizeArrow($oneArrow);
        $twoArrow = $this->normalizeArrow($twoArrow);

        return DB::transaction(function () use ($match, $oneArrow, $twoArrow, $actorId): EventEliminationMatch {
            $match = EventEliminationMatch::query()->with('bracket')->lockForUpdate()->findOrFail($match->id);
            if ($match->status !== 'awaiting_shoot_off') {
                throw ValidationException::withMessages(['shoot_off'=>'此場目前不需要輸入加射。']);
            }
            $attempt = ((int) $match->shootOffs()->max('attempt_number')) + 1;
            $oneValue = $this->value($oneArrow);
            $twoValue = $this->value($twoArrow);
            $winnerId = null;
            $status = 'pending_judge';
            $decisionType = null;
            if ($oneValue !== $twoValue) {
                $winnerId = $oneValue > $twoValue ? $match->participant_one_registration_id : $match->participant_two_registration_id;
                $status = 'resolved';
                $decisionType = 'score';
            }

            $shootOff = $match->shootOffs()->create([
                'attempt_number'=>$attempt,
                'participant_one_arrow'=>$oneArrow,
                'participant_two_arrow'=>$twoArrow,
                'participant_one_value'=>$oneValue,
                'participant_two_value'=>$twoValue,
                'status'=>$status,
                'decision_type'=>$decisionType,
                'winner_registration_id'=>$winnerId,
                'recorded_by'=>$actorId,
            ]);

            if ($winnerId) {
                $this->completeMatch($match, $winnerId);
            } else {
                $match->update(['status'=>'awaiting_judge']);
            }
            $this->audit($match, $actorId, 'elimination.shoot_off_recorded', [
                'attempt'=>$attempt, 'arrows'=>[$oneArrow, $twoArrow],
                'values'=>[$oneValue, $twoValue], 'result'=>$status,
            ], $shootOff);

            return $match->fresh(['shootOffs', 'participantOneEntry', 'participantTwoEntry']);
        });
    }

    public function adjudicate(EventEliminationMatch $match, string $decision, string $note, int $judgeId): EventEliminationMatch
    {
        if (! in_array($decision, ['participant_one', 'participant_two', 're_shoot'], true)) {
            throw ValidationException::withMessages(['decision'=>'主裁判判定選項無效。']);
        }
        if (trim($note) === '') {
            throw ValidationException::withMessages(['decision_note'=>'主裁判必須填寫判定說明。']);
        }

        return DB::transaction(function () use ($match, $decision, $note, $judgeId): EventEliminationMatch {
            $match = EventEliminationMatch::query()->with('bracket')->lockForUpdate()->findOrFail($match->id);
            if ($match->status !== 'awaiting_judge') {
                throw ValidationException::withMessages(['decision'=>'此場目前不需要主裁判判定。']);
            }
            $shootOff = $match->shootOffs()->where('status', 'pending_judge')->lockForUpdate()->latest('attempt_number')->firstOrFail();
            $winnerId = match ($decision) {
                'participant_one'=>$match->participant_one_registration_id,
                'participant_two'=>$match->participant_two_registration_id,
                default=>null,
            };
            $shootOff->update([
                'status'=>$winnerId ? 'resolved' : 're_shoot',
                'decision_type'=>$winnerId ? 'closest_to_center' : 'equal_distance',
                'winner_registration_id'=>$winnerId,
                'decision_note'=>trim($note),
                'judged_by'=>$judgeId,
                'judged_at'=>now(),
            ]);

            if ($winnerId) {
                $this->completeMatch($match, $winnerId);
            } else {
                $match->update(['status'=>'awaiting_shoot_off']);
            }
            $this->audit($match, $judgeId, 'elimination.shoot_off_adjudicated', [
                'attempt'=>$shootOff->attempt_number, 'decision'=>$decision,
                'winner_registration_id'=>$winnerId, 'note'=>trim($note),
            ], $shootOff);

            return $match->fresh(['shootOffs', 'participantOneEntry', 'participantTwoEntry']);
        });
    }

    private function completeMatch(EventEliminationMatch $match, int $winnerId): void
    {
        $loserId = $winnerId === $match->participant_one_registration_id
            ? $match->participant_two_registration_id
            : $match->participant_one_registration_id;
        $match->update([
            'status'=>'completed', 'winner_registration_id'=>$winnerId,
            'loser_registration_id'=>$loserId, 'completed_at'=>now(),
        ]);
        $this->progression->advance($match->fresh(), $winnerId);
    }

    private function audit(EventEliminationMatch $match, ?int $userId, string $action, array $metadata, EventEliminationShootOff $shootOff): void
    {
        EventAuditLog::create([
            'event_id'=>$match->bracket->event_id, 'user_id'=>$userId, 'action'=>$action,
            'subject_type'=>EventEliminationShootOff::class, 'subject_id'=>$shootOff->id,
            'metadata'=>$metadata,
        ]);
    }

    private function normalizeArrow(string $arrow): string
    {
        $arrow = strtoupper(trim($arrow));
        if (! in_array($arrow, ['X', '10', '9', '8', '7', '6', '5', '4', '3', '2', '1', 'M'], true)) {
            throw ValidationException::withMessages(['shoot_off'=>'加射箭值只能是 X、10～1 或 M。']);
        }
        return $arrow;
    }

    private function value(string $arrow): int
    {
        return $arrow === 'X' ? 10 : ($arrow === 'M' ? 0 : (int) $arrow);
    }
}
