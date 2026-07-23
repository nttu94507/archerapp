<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventBadge;
use App\Models\EventRegistration;
use App\Models\EventStaff;
use App\Models\UserEventBadge;
use Illuminate\Support\Facades\DB;

class EventBadgeAwardService
{
    public function awardTeamFor(EventStaff $staff): int
    {
        if ($staff->status !== 'active') return 0;

        $rule = $staff->role === 'volunteer' ? 'volunteer' : (in_array($staff->role, ['owner','manager','staff'], true) ? 'staff' : null);
        if (! $rule) return 0;
        $badges = EventBadge::where('event_id', $staff->event_id)->where('award_rule', $rule)->where('is_active', true)->get()
            ->filter(fn (EventBadge $badge) => $rule === 'volunteer' || in_array($staff->role, $badge->staff_roles ?: ['owner','manager','staff'], true));
        foreach ($badges as $badge) $this->award($badge, $staff->user_id, $staff->role === 'volunteer' ? 'volunteer' : 'staff');
        return $badges->count();
    }

    public function awardExistingTeamFor(EventBadge $badge): int
    {
        if (! in_array($badge->award_rule, ['staff', 'volunteer'], true)) return 0;
        $roles = $badge->award_rule === 'volunteer' ? ['volunteer'] : ($badge->staff_roles ?: ['owner', 'manager', 'staff']);
        $members = EventStaff::where('event_id', $badge->event_id)->where('status', 'active')->whereIn('role', $roles)->get();
        foreach ($members as $member) $this->awardTeamFor($member);
        return $members->count();
    }

    public function awardAttendanceFor(EventRegistration $registration): int
    {
        if (! in_array($registration->status, ['registered', 'checked_in'], true)
            || ! $registration->checked_in_at
            || ! in_array($registration->payment_status, ['paid', 'exempt'], true)) return 0;

        $badges = EventBadge::where('event_id', $registration->event_id)
            ->where('award_rule', 'attendance')->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('event_group_id')->orWhere('event_group_id', $registration->event_group_id))->get();

        foreach ($badges as $badge) $this->award($badge, $registration->user_id, 'attendance');
        return $badges->count();
    }

    public function awardPlacementsFor(Event $event): int
    {
        $count = 0;
        $badges = $event->badges()->where('award_rule', 'placement')->where('is_active', true)->get();
        foreach ($badges as $badge) {
            $ranked = $event->registrations()->with(['scoreEntries','event_group'])
                ->where('event_group_id', $badge->event_group_id)
                ->whereIn('status', ['registered', 'checked_in'])
                ->whereNotNull('score_verified_at')->whereNotNull('result_published_at')->get()
                ->sortByDesc(fn ($registration) => $registration->scoreEntries->sum('end_total'))->values();
            $rank = 1; $winnerIds = [];
            foreach ($ranked->groupBy(fn ($registration) => (string) $registration->scoreEntries->sum('end_total')) as $sameScore) {
                if ($rank === (int) $badge->placement) {
                    foreach ($sameScore as $winner) {
                        $winnerIds[] = $winner->user_id;
                        if ($this->award($badge, $winner->user_id, 'placement', null, null, [
                            'group_name_snapshot'=>$winner->event_group?->name,
                            'placement_snapshot'=>$badge->placement,
                            'score_snapshot'=>$winner->scoreEntries->sum('end_total'),
                            'criteria_snapshot'=>'正式成績第 '.$badge->placement.' 名',
                        ], true)) $count++;
                    }
                    break;
                }
                $rank += $sameScore->count();
            }
            $badge->awards()->where('award_source','placement')->whereNull('revoked_at')->whereNotIn('user_id',$winnerIds)->update([
                'revoked_at'=>now(), 'revoked_reason'=>'正式成績修正，名次重新判定',
            ]);
        }
        return $count;
    }

    public function award(EventBadge $badge, int $userId, string $source, ?int $actorId = null, ?string $note = null, array $snapshots = [], bool $allowRestore = false): bool
    {
        return DB::transaction(function () use ($badge,$userId,$source,$actorId,$note,$snapshots,$allowRestore): bool {
            $locked = EventBadge::whereKey($badge->id)->lockForUpdate()->firstOrFail();
            $award = UserEventBadge::firstOrNew(['event_badge_id'=>$locked->id,'user_id'=>$userId]);
            $wasActive = $award->exists && $award->revoked_at === null;
            if ($wasActive || ($award->exists && ! $allowRestore && $source !== 'manual')) return false;
            if (! $award->exists && $locked->max_supply !== null && $locked->awards()->count() >= $locked->max_supply) return false;
            $serial = $award->limited_serial;
            if (! $award->exists && $locked->max_supply !== null) $serial = ((int)$locked->awards()->max('limited_serial')) + 1;
            $award->fill(array_merge([
                'awarded_by'=>$actorId,'awarded_at'=>now(),'award_source'=>$source,'limited_serial'=>$serial,'award_note'=>$note,
                'issuer_name_snapshot'=>$locked->issuer_name ?: $locked->event?->organizer ?: 'ArrowTrack',
                'event_name_snapshot'=>$locked->display_activity_name,
                'criteria_snapshot'=>$this->criteriaLabel($locked,$source),
                'revoked_by'=>null,'revoked_at'=>null,'revoked_reason'=>null,
            ],$snapshots))->save();
            return true;
        });
    }

    private function criteriaLabel(EventBadge $badge, string $source): string
    {
        return match ($source) {
            'attendance'=>'完成繳費並報到','placement'=>'正式成績名次','staff'=>'加入賽事工作團隊',
            'volunteer'=>'成為賽事志工','platform'=>'平台官方發放',default=>'主辦方授予',
        };
    }
}
