<?php

namespace App\Services;

use App\Models\EventAuditLog;
use App\Models\EventEliminationMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EliminationMatchRecoveryService
{
    public function __construct(private readonly EliminationMatchProgressionService $progression) {}

    public function forceWinner(EventEliminationMatch $match, string $side, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($match, $side, $reason, $actorId): void {
            $match = EventEliminationMatch::with('bracket')->lockForUpdate()->findOrFail($match->id);
            $this->assertReason($reason); $this->assertDownstreamSafe($match);
            $team = in_array($match->bracket->category, ['team', 'mixed_team'], true);
            $one = $team ? $match->participant_one_team_id : $match->participant_one_registration_id;
            $two = $team ? $match->participant_two_team_id : $match->participant_two_registration_id;
            if (! $one || ! $two) throw ValidationException::withMessages(['recovery'=>'雙方參賽者尚未確定。']);
            $winner = $side === 'participant_one' ? $one : $two;
            $loser = $winner === $one ? $two : $one;
            $before = $this->snapshot($match); $this->detach($match);
            $match->update(['status'=>'completed', 'completed_at'=>now(),
                'winner_registration_id'=>$team ? null : $winner, 'loser_registration_id'=>$team ? null : $loser,
                'winner_team_id'=>$team ? $winner : null, 'loser_team_id'=>$team ? $loser : null]);
            $this->progression->advance($match->fresh(), $winner);
            $this->audit($match, $actorId, 'elimination.match_winner_forced', $reason, $before);
        });
    }

    public function reopenShootOff(EventEliminationMatch $match, string $reason, int $actorId): void
    {
        DB::transaction(function () use ($match, $reason, $actorId): void {
            $match = EventEliminationMatch::with('bracket')->lockForUpdate()->findOrFail($match->id);
            $this->assertReason($reason); $this->assertDownstreamSafe($match);
            $before = $this->snapshot($match); $this->detach($match);
            $match->shootOffs()->where('status', 'pending_judge')->update([
                'status'=>'re_shoot', 'decision_type'=>'manual_reopen',
                'decision_note'=>trim($reason), 'judged_by'=>$actorId, 'judged_at'=>now(),
            ]);
            $match->update(['status'=>'awaiting_shoot_off', 'completed_at'=>null,
                'winner_registration_id'=>null, 'loser_registration_id'=>null,
                'winner_team_id'=>null, 'loser_team_id'=>null]);
            $this->audit($match, $actorId, 'elimination.match_shoot_off_reopened', $reason, $before);
        });
    }

    public function resynchronize(EventEliminationMatch $match, string $reason, int $actorId): void
    {
        $this->assertReason($reason); $before = $this->snapshot($match->fresh());
        $this->progression->synchronizeBracket($match->bracket);
        $this->audit($match->fresh(), $actorId, 'elimination.bracket_resynchronized', $reason, $before);
    }

    private function assertDownstreamSafe(EventEliminationMatch $match): void
    {
        foreach ([$match->nextMatch, $match->loserNextMatch] as $destination) {
            if (! $destination) continue;
            if ($destination->sets()->exists() || $destination->ends()->exists() || $destination->shootOffs()->exists()
                || in_array($destination->status, ['in_progress', 'awaiting_shoot_off', 'awaiting_judge', 'completed'], true)) {
                throw ValidationException::withMessages(['recovery'=>'受影響的下一輪已開始或完成，不能直接覆蓋；請先處理後續場次。']);
            }
        }
    }

    private function detach(EventEliminationMatch $match): void
    {
        $team = in_array($match->bracket->category, ['team', 'mixed_team'], true);
        foreach ([[$match->nextMatch, $match->next_slot], [$match->loserNextMatch, $match->loser_next_slot]] as [$destination, $slot]) {
            if (! $destination || ! $slot) continue;
            $word = $slot === 1 ? 'one' : 'two';
            $destination->update($team
                ? ["participant_{$word}_team_id"=>null, "participant_{$word}_seed"=>null]
                : ["participant_{$word}_snapshot_entry_id"=>null, "participant_{$word}_registration_id"=>null, "participant_{$word}_seed"=>null]);
            $destination->refresh();
            $both = $team ? ($destination->participant_one_team_id && $destination->participant_two_team_id)
                : ($destination->participant_one_registration_id && $destination->participant_two_registration_id);
            $destination->update(['status'=>$both ? 'ready' : 'pending']);
        }
    }

    private function assertReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 3) throw ValidationException::withMessages(['reason'=>'異常處理原因至少需要 3 個字。']);
    }

    private function snapshot(EventEliminationMatch $match): array
    {
        return $match->only(['status', 'winner_registration_id', 'loser_registration_id', 'winner_team_id', 'loser_team_id', 'completed_at']);
    }

    private function audit(EventEliminationMatch $match, int $actorId, string $action, string $reason, array $before): void
    {
        EventAuditLog::create(['event_id'=>$match->bracket->event_id, 'user_id'=>$actorId, 'action'=>$action,
            'subject_type'=>EventEliminationMatch::class, 'subject_id'=>$match->id,
            'metadata'=>['reason'=>trim($reason), 'before'=>$before, 'after'=>$this->snapshot($match->fresh())]]);
    }
}
