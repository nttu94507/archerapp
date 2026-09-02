<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAuditLog;
use App\Models\EventEliminationMatch;
use App\Models\EventScoringTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventCompletionService
{
    /** @return array{ready:bool, blockers:array<int,string>} */
    public function inspect(Event $event): array
    {
        $blockers = [];
        $registrations = $event->registrations()
            ->whereIn('status', ['registered', 'checked_in', 'no_show']);

        if ((clone $registrations)->doesntExist()) {
            $blockers[] = '賽事沒有可結案的參賽選手。';
        } else {
            $unpublished = (clone $registrations)->whereNull('result_published_at')->count();
            if ($unpublished > 0) $blockers[] = '仍有 '.$unpublished.' 位選手的排名成績尚未正式發布。';
        }

        $unfinishedTargets = EventScoringTarget::query()
            ->whereHas('session', fn ($query) => $query->where('event_id', $event->id))
            ->whereNotIn('status', ['completed', 'dns'])
            ->count();
        if ($unfinishedTargets > 0) $blockers[] = '仍有 '.$unfinishedTargets.' 個排名賽靶位尚未完成。';

        $brackets = $event->eliminationBrackets()->with(['group', 'matches'])->get();
        foreach ($brackets as $bracket) {
            $main = $bracket->matches->where('match_type', 'main');
            $final = $main->sortByDesc('round_number')->first();
            $bronze = $bracket->matches->firstWhere('match_type', 'bronze');
            $unresolved = $bracket->matches->whereIn('status', [
                'ready', 'in_progress', 'awaiting_shoot_off', 'awaiting_judge',
            ])->count();

            $finalHasWinner = $final && ($final->winner_registration_id || $final->winner_team_id);
            $bronzeHasWinner = ! $bronze || $bronze->winner_registration_id || $bronze->winner_team_id;

            if (! $finalHasWinner) {
                $blockers[] = $bracket->group->name.'：冠軍賽尚未完成。';
            }
            if (! $bronzeHasWinner) {
                $blockers[] = $bracket->group->name.'：季軍賽尚未完成或尚未判定輪空。';
            }
            if ($unresolved > 0) {
                $blockers[] = $bracket->group->name.'：仍有 '.$unresolved.' 場對抗賽進行中或等待判定。';
            }
        }

        return ['ready'=>$blockers === [], 'blockers'=>array_values(array_unique($blockers))];
    }

    public function complete(Event $event, int $actorId): void
    {
        DB::transaction(function () use ($event, $actorId): void {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $check = $this->inspect($locked);
            if (! $check['ready']) {
                throw ValidationException::withMessages(['completion'=>implode(' ', $check['blockers'])]);
            }

            $now = now();
            $locked->eliminationBrackets()->update(['status'=>'completed', 'completed_at'=>$now]);
            $locked->phases()->where('type', 'elimination')->update(['status'=>'completed', 'completed_at'=>$now]);

            EventScoringTarget::query()
                ->whereHas('session', fn ($query) => $query->where('event_id', $locked->id))
                ->get()->each(fn (EventScoringTarget $target) => $target->update([
                    'access_token'=>(string) Str::uuid(),
                    'device_pin'=>(string) random_int(100000, 999999),
                    'device_token_hash'=>null,
                    'device_bound_at'=>null,
                    'device_last_seen_at'=>null,
                    'device_user_agent'=>null,
                ]));

            EventEliminationMatch::query()
                ->whereHas('bracket', fn ($query) => $query->where('event_id', $locked->id))
                ->get()->each(fn (EventEliminationMatch $match) => $match->update([
                    'access_token'=>(string) Str::uuid(),
                    'device_pin'=>(string) random_int(100000, 999999),
                    'device_token_hash'=>null,
                    'device_bound_at'=>null,
                    'device_last_seen_at'=>null,
                    'device_user_agent'=>null,
                ]));

            $locked->update(['completed_at'=>$now]);
            EventAuditLog::create([
                'event_id'=>$locked->id,
                'user_id'=>$actorId,
                'action'=>'event.completed',
                'subject_type'=>Event::class,
                'subject_id'=>$locked->id,
                'metadata'=>[
                    'qualification_groups'=>$locked->groups()->count(),
                    'elimination_brackets'=>$locked->eliminationBrackets()->count(),
                ],
            ]);
        });
    }
}
