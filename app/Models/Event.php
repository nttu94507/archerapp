<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Support\EventPlanCatalog;

class Event extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (! $event->uuid) {
                $event->uuid = (string) Str::uuid();
            }
            $event->plan_code ??= EventPlanCatalog::FREE;
            $event->plan_status ??= EventPlanCatalog::STATUS_ACTIVE;
            $event->plan_limits_snapshot ??= EventPlanCatalog::limits($event->plan_code);
            $event->plan_features_snapshot ??= EventPlanCatalog::features($event->plan_code);
            $event->plan_activated_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    //
    protected $fillable = [
        'name', 'start_date','end_date', 'mode', 'verified', 'level',
        'organizer', 'reg_start', 'reg_end',
        'venue', 'map_link', 'lat', 'lng', 'status', 'published_at',
        'visibility',
        'cancelled_at', 'completed_at', 'review_note',
        'plan_code', 'plan_status', 'plan_limits_snapshot', 'plan_features_snapshot',
        'plan_activated_at', 'plan_expires_at', 'plan_order_reference',
    ];
    protected $casts = [
        'verified' => 'boolean',
        'start_date' => 'date', 'end_date' => 'date',
        'reg_start' => 'datetime', 'reg_end' => 'datetime',
        'published_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime',
        'plan_limits_snapshot'=>'array', 'plan_features_snapshot'=>'array',
        'plan_activated_at'=>'datetime', 'plan_expires_at'=>'datetime',
    ];

    // app/Models/Event.php
    public function groups() {
        return $this->hasMany(EventGroup::class);
    }

    public function staff() {
        return $this->hasMany(EventStaff::class);
    }

    public function badges() {
        return $this->hasMany(EventBadge::class);
    }

    public function registrations() {
        return $this->hasMany(EventRegistration::class);
    }

    public function scoringSessions() {
        return $this->hasMany(EventScoringSession::class);
    }

    public function phases() {
        return $this->hasMany(EventPhase::class)->orderBy('sequence');
    }

    public function rankingSnapshots() {
        return $this->hasMany(EventRankingSnapshot::class);
    }

    public function eliminationBrackets() {
        return $this->hasMany(EventEliminationBracket::class);
    }

    public function auditLogs() {
        return $this->hasMany(EventAuditLog::class)->latest();
    }

    public function scopePublished($query) {
        return $query->where('status', 'approved')->whereNotNull('published_at')->whereNull('cancelled_at');
    }

    public function isPublished(): bool {
        return $this->status === 'approved' && $this->published_at !== null && $this->cancelled_at === null;
    }

    public function isOfficiallyCompleted(): bool
    {
        return $this->completed_at !== null
            || $this->auditLogs()->where('action', 'event.completed')->exists();
    }

    public function canUpgradeToEventPass(): bool
    {
        return $this->isFreePlan()
            && $this->cancelled_at === null
            && ! $this->isOfficiallyCompleted();
    }

    public function eventPassUpgradeBlockReason(): ?string
    {
        if (! $this->isFreePlan()) return '這場賽事已啟用進階功能。';
        if ($this->cancelled_at !== null) return '賽事已取消，無法購買單場升級。';
        if ($this->isOfficiallyCompleted()) return '賽事已正式完成，無法再套用執行中的進階功能。';

        return null;
    }

    public function hasPlanFeature(string $feature): bool
    {
        if (! $this->planIsActive()) {
            return false;
        }

        return (bool) ($this->plan_features_snapshot[$feature]
            ?? EventPlanCatalog::features($this->plan_code)[$feature]
            ?? false);
    }

    public function planLimit(string $resource): ?int
    {
        $value = $this->plan_limits_snapshot[$resource]
            ?? EventPlanCatalog::limits($this->plan_code)[$resource]
            ?? null;

        return $value === null ? null : (int) $value;
    }

    public function isFreePlan(): bool
    {
        return $this->plan_code === EventPlanCatalog::FREE;
    }

    public function planIsActive(): bool
    {
        return $this->plan_status === EventPlanCatalog::STATUS_ACTIVE
            && ($this->plan_expires_at === null || $this->plan_expires_at->isFuture());
    }

    public function registrationStatus(?Carbon $at = null): string
    {
        $hasScoringSession = $this->relationLoaded('scoringSessions')
            ? $this->scoringSessions->isNotEmpty()
            : $this->scoringSessions()->exists();
        if ($hasScoringSession) return 'closed';

        $at ??= now();
        $groups = $this->relationLoaded('groups') ? $this->groups : $this->groups()->with('event')->get();
        if ($groups->isEmpty()) return 'unset';

        $windows = $groups->map(function (EventGroup $group): array {
            $group->setRelation('event', $this);
            return [$group->effectiveRegStart(), $group->effectiveRegEnd()];
        })
            ->filter(fn (array $window) => $window[0] !== null && $window[1] !== null);
        if ($windows->isEmpty()) return 'unset';
        if ($windows->contains(fn (array $window) => $at->between($window[0], $window[1]))) return 'open';
        if ($windows->contains(fn (array $window) => $at->lt($window[0]))) return 'upcoming';

        return 'closed';
    }

    public function registrationClosesAt(): ?Carbon
    {
        $groups = $this->relationLoaded('groups') ? $this->groups : $this->groups()->with('event')->get();
        return $groups->map(function (EventGroup $group) {
            $group->setRelation('event', $this);
            return $group->effectiveRegEnd();
        })->filter()->max();
    }
}
