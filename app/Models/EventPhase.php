<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventPhase extends Model
{
    protected static function booted(): void
    {
        static::creating(function (EventPhase $phase): void {
            if (! $phase->uuid) {
                $phase->uuid = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'uuid', 'event_id', 'event_group_id', 'name', 'type', 'sequence',
        'scoring_mode', 'status', 'total_arrows', 'arrows_per_end',
        'max_sets', 'set_points_to_win', 'settings', 'locked_at',
        'started_at', 'completed_at', 'published_at', 'created_by',
    ];

    protected $casts = [
        'settings'=>'array',
        'locked_at'=>'datetime',
        'started_at'=>'datetime',
        'completed_at'=>'datetime',
        'published_at'=>'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function group()
    {
        return $this->belongsTo(EventGroup::class, 'event_group_id');
    }

    public function scoringSessions()
    {
        return $this->hasMany(EventScoringSession::class);
    }

    public function rankingSnapshots()
    {
        return $this->hasMany(EventRankingSnapshot::class)->latest('version');
    }

    public function eliminationBracket()
    {
        return $this->hasOne(EventEliminationBracket::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
