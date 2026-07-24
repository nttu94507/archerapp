<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBadgeClaim extends Model
{
    protected $fillable = [
        'event_badge_id', 'user_id', 'status', 'is_eligible', 'eligibility_note',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'is_eligible' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function badge(): BelongsTo
    {
        return $this->belongsTo(EventBadge::class, 'event_badge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
