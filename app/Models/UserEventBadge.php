<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEventBadge extends Model
{
    protected $fillable = [
        'public_id', 'event_badge_id', 'user_id', 'event_badge_claim_id', 'badge_campaign_id', 'awarded_by', 'awarded_at',
        'award_source', 'limited_serial', 'award_note', 'issuer_name_snapshot', 'event_name_snapshot', 'group_name_snapshot', 'criteria_snapshot', 'placement_snapshot', 'score_snapshot', 'revoked_by', 'revoked_at', 'revoked_reason',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void { static::creating(fn (self $award) => $award->public_id ??= (string) \Illuminate\Support\Str::uuid()); }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(EventBadge::class, 'event_badge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(EventBadgeClaim::class, 'event_badge_claim_id');
    }
}
