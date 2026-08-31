<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewManagement(User $user, Event $event): bool
    {
        return $this->hasRole($user, $event, ['owner', 'manager', 'staff', 'score_manager', 'judge', 'chief_judge', 'volunteer', 'viewer']);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->canOperate($user) && ! $event->isOfficiallyCompleted() && $this->hasRole($user, $event, ['owner', 'manager']);
    }

    public function manageGroups(User $user, Event $event): bool
    {
        return $this->canOperate($user) && ! $event->isOfficiallyCompleted() && $this->hasRole($user, $event, ['owner', 'manager']);
    }

    public function manageRegistrations(User $user, Event $event): bool
    {
        return $this->canOperate($user) && ! $event->isOfficiallyCompleted() && $this->hasRole($user, $event, ['owner', 'manager', 'staff']);
    }

    public function manageScores(User $user, Event $event): bool
    {
        return $this->canOperate($user) && ! $event->isOfficiallyCompleted() && $this->hasRole($user, $event, ['owner', 'manager', 'staff']);
    }

    public function viewResults(User $user, Event $event): bool
    {
        return $this->canOperate($user) && $this->hasRole($user, $event, ['owner', 'manager', 'staff', 'score_manager', 'chief_judge']);
    }

    public function manageScoreCorrections(User $user, Event $event): bool
    {
        return $this->canOperate($user) && $this->hasRole($user, $event, ['owner', 'manager', 'score_manager', 'chief_judge']);
    }

    public function approveResults(User $user, Event $event): bool
    {
        return $this->canOperate($user) && $this->hasRole($user, $event, ['owner', 'score_manager', 'chief_judge']);
    }

    public function manageStaff(User $user, Event $event): bool
    {
        return $this->canOperate($user) && ! $event->isOfficiallyCompleted() && $this->hasRole($user, $event, ['owner', 'manager']);
    }

    public function viewAuditLogs(User $user, Event $event): bool
    {
        return $this->canOperate($user) && $this->hasRole($user, $event, ['owner', 'manager']);
    }

    public function manageJudging(User $user, Event $event): bool
    {
        return $this->canOperate($user) && ! $event->isOfficiallyCompleted() && $this->hasRole($user, $event, ['owner', 'manager', 'judge', 'chief_judge']);
    }

    public function manageShootOff(User $user, Event $event): bool
    {
        return $this->canOperate($user) && ! $event->isOfficiallyCompleted() && $this->hasRole($user, $event, ['owner', 'manager', 'staff', 'score_manager', 'chief_judge']);
    }

    public function adjudicateShootOff(User $user, Event $event): bool
    {
        return $this->canOperate($user) && ! $event->isOfficiallyCompleted() && $this->hasRole($user, $event, ['chief_judge']);
    }

    private function canOperate(User $user): bool
    {
        return $user->organizerProfile()->where('status', 'suspended')->doesntExist();
    }

    private function hasRole(User $user, Event $event, array $roles): bool
    {
        return $event->staff()->where('user_id', $user->id)->where('status', 'active')->whereIn('role', $roles)->exists();
    }
}
