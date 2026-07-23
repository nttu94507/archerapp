<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventBadge extends Model
{
    protected $fillable = [
        'event_id', 'event_group_id', 'created_by', 'issuer_type', 'issuer_name', 'external_activity_name', 'external_activity_date', 'external_activity_location', 'name', 'description', 'icon_path', 'type', 'eligibility', 'award_rule', 'staff_roles', 'placement', 'max_supply',
        'claim_token', 'claim_enabled', 'claim_starts_at', 'claim_ends_at', 'is_active',
    ];

    protected $casts = [
        'claim_enabled' => 'boolean',
        'claim_starts_at' => 'datetime',
        'claim_ends_at' => 'datetime',
        'is_active' => 'boolean',
        'staff_roles' => 'array',
        'external_activity_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventBadge $badge): void {
            $badge->claim_token ??= (string) Str::uuid();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventGroup(): BelongsTo { return $this->belongsTo(EventGroup::class); }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(EventBadgeClaim::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(UserEventBadge::class);
    }

    public function isClaimOpen(): bool
    {
        return $this->is_active
            && $this->claim_enabled
            && ($this->claim_starts_at === null || $this->claim_starts_at->isPast())
            && ($this->claim_ends_at === null || $this->claim_ends_at->isFuture());
    }

    public function getIconUrlAttribute(): string
    {
        return $this->icon_path ? asset('storage/'.$this->icon_path) : asset('images/default-badge.svg');
    }

    public function isAtCapacity(): bool { return $this->max_supply !== null && $this->awards()->count() >= $this->max_supply; }
    public function getDisplayActivityNameAttribute(): ?string { return $this->event?->name ?? $this->external_activity_name; }
}
