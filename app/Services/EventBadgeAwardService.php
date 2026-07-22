<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventBadge;
use App\Models\EventRegistration;
use App\Models\EventStaff;
use App\Models\UserEventBadge;

class EventBadgeAwardService
{
    public function awardTeamFor(EventStaff $staff): int
    {
        if ($staff->status !== 'active') return 0;

        $rules = match ($staff->role) {
            'owner', 'manager', 'staff' => ['staff'],
            'volunteer' => ['volunteer'],
            default => [],
        };
        if ($rules === []) return 0;

        $badges = EventBadge::where('event_id', $staff->event_id)->whereIn('award_rule', $rules)->where('is_active', true)->get();
        foreach ($badges as $badge) $this->award($badge, $staff->user_id, $staff->role === 'volunteer' ? 'volunteer' : 'staff');
        return $badges->count();
    }

    public function awardExistingTeamFor(EventBadge $badge): int
    {
        if (! in_array($badge->award_rule, ['staff', 'volunteer'], true)) return 0;
        $roles = $badge->award_rule === 'volunteer' ? ['volunteer'] : ['owner', 'manager', 'staff'];
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
            $ranked = $event->registrations()->with('scoreEntries')
                ->where('event_group_id', $badge->event_group_id)
                ->whereIn('status', ['registered', 'checked_in'])
                ->whereNotNull('score_verified_at')->whereNotNull('result_published_at')->get()
                ->sortByDesc(fn ($registration) => $registration->scoreEntries->sum('end_total'))->values();
            $rank = 1;
            foreach ($ranked->groupBy(fn ($registration) => (string) $registration->scoreEntries->sum('end_total')) as $sameScore) {
                if ($rank === (int) $badge->placement) {
                    foreach ($sameScore as $winner) if ($this->award($badge, $winner->user_id, 'placement')) $count++;
                    break;
                }
                $rank += $sameScore->count();
            }
        }
        return $count;
    }

    public function award(EventBadge $badge, int $userId, string $source, ?int $actorId = null, ?string $note = null): bool
    {
        $award = UserEventBadge::firstOrNew(['event_badge_id' => $badge->id, 'user_id' => $userId]);
        $wasActive = $award->exists && $award->revoked_at === null;
        if ($award->exists && $award->revoked_at !== null && $source !== 'manual') return false;
        $award->fill(['awarded_by'=>$actorId, 'awarded_at'=>now(), 'award_source'=>$source, 'award_note'=>$note, 'revoked_by'=>null, 'revoked_at'=>null, 'revoked_reason'=>null])->save();
        return ! $wasActive;
    }
}
