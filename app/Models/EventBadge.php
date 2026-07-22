<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventBadge extends Model
{
    protected $fillable = [
        'event_id', 'created_by', 'name', 'description', 'type', 'eligibility',
        'claim_token', 'claim_enabled', 'claim_starts_at', 'claim_ends_at', 'is_active',
    ];

    protected $casts = [
        'claim_enabled' => 'boolean',
        'claim_starts_at' => 'datetime',
        'claim_ends_at' => 'datetime',
        'is_active' => 'boolean',
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
}
