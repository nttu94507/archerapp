<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (! $event->uuid) {
                $event->uuid = (string) Str::uuid();
            }
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
        'cancelled_at', 'completed_at', 'review_note',
    ];
    protected $casts = [
        'verified' => 'boolean',
        'start_date' => 'date', 'end_date' => 'date',
        'reg_start' => 'datetime', 'reg_end' => 'datetime',
        'published_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime',
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

    public function auditLogs() {
        return $this->hasMany(EventAuditLog::class)->latest();
    }

    public function scopePublished($query) {
        return $query->where('status', 'approved')->whereNotNull('published_at')->whereNull('cancelled_at');
    }

    public function isPublished(): bool {
        return $this->status === 'approved' && $this->published_at !== null && $this->cancelled_at === null;
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
