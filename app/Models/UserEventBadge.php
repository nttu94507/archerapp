<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEventBadge extends Model
{
    protected $fillable = [
        'event_badge_id', 'user_id', 'event_badge_claim_id', 'awarded_by', 'awarded_at',
        'award_source', 'award_note', 'revoked_by', 'revoked_at', 'revoked_reason',
    ];

    protected $casts = [
        'awarded_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

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
