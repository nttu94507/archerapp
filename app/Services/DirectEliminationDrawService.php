<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventGroup;
use App\Models\EventPhase;
use App\Models\EventRankingSnapshot;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectEliminationDrawService
{
    public function draw(Event $event, EventGroup $group, ?int $actorId = null): EventRankingSnapshot
    {
        if ($event->competition_format !== 'elimination_only') {
            throw ValidationException::withMessages(['event_group_id'=>'只有純對抗賽可直接隨機抽籤。']);
        }

        return DB::transaction(function () use ($event, $group, $actorId): EventRankingSnapshot {
            $existing = EventRankingSnapshot::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->where('status', 'locked')
                ->whereNull('superseded_at')
                ->with('entries')
                ->lockForUpdate()
                ->first();
            if ($existing) return $existing;

            $registrations = EventRegistration::query()
                ->where('event_id', $event->id)
                ->where('event_group_id', $group->id)
                ->whereIn('status', ['registered', 'checked_in'])
                ->lockForUpdate()
                ->get()
                ->shuffle()
                ->values();
            if ($registrations->count() < 2) {
                throw ValidationException::withMessages(['event_group_id'=>'至少需要 2 名已報名選手才能抽籤。']);
            }

            $now = now();
            $phase = EventPhase::create([
                'event_id'=>$event->id,
                'event_group_id'=>$group->id,
                'name'=>$group->name.' 隨機抽籤',
                'type'=>'seeding',
                'sequence'=>1,
                'scoring_mode'=>'none',
                'status'=>'published',
                'settings'=>['method'=>'random_draw'],
                'locked_at'=>$now,
                'completed_at'=>$now,
                'published_at'=>$now,
                'created_by'=>$actorId,
            ]);
            $order = $registrations->pluck('id')->all();
            $snapshot = EventRankingSnapshot::create([
                'event_id'=>$event->id,
                'event_group_id'=>$group->id,
                'event_phase_id'=>$phase->id,
                'version'=>1,
                'status'=>'locked',
                'source_hash'=>hash('sha256', json_encode($order, JSON_THROW_ON_ERROR)),
                'ranking_rule'=>['method'=>'random_draw', 'order'=>'locked_after_draw'],
                'locked_at'=>$now,
                'created_by'=>$actorId,
            ]);
            foreach ($registrations as $index => $registration) {
                $seed = $index + 1;
                $snapshot->entries()->create([
                    'event_registration_id'=>$registration->id,
                    'user_id'=>$registration->user_id,
                    'rank_position'=>$seed,
                    'seed_position'=>$seed,
                    'total_score'=>0,
                    'ten_count'=>0,
                    'x_count'=>0,
                    'result_status'=>'drawn',
                    'is_eligible'=>true,
                    'requires_tiebreak'=>false,
                    'athlete_name'=>$registration->name,
                    'team_name'=>$registration->team_name,
                ]);
            }

            $event->update(['reg_end'=>$now]);
            $event->groups()->update(['reg_end'=>$now]);
            EventAuditLog::create([
                'event_id'=>$event->id,
                'user_id'=>$actorId,
                'action'=>'elimination.random_draw_locked',
                'subject_type'=>EventRankingSnapshot::class,
                'subject_id'=>$snapshot->id,
                'metadata'=>['group_id'=>$group->id, 'entrants'=>$registrations->count(), 'order'=>$order],
            ]);

            return $snapshot->load('entries');
        });
    }
}
