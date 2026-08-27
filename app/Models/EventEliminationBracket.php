<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventEliminationBracket extends Model
{
    protected static function booted(): void
    {
        static::creating(function (EventEliminationBracket $bracket): void {
            $bracket->uuid ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'uuid', 'event_id', 'event_group_id', 'event_phase_id', 'event_ranking_snapshot_id',
        'name', 'category', 'scoring_mode', 'bracket_size', 'status', 'visibility', 'bronze_match_enabled',
        'locked_at', 'started_at', 'completed_at', 'published_at', 'created_by',
    ];

    protected $casts = [
        'bronze_match_enabled'=>'boolean',
        'locked_at'=>'datetime',
        'started_at'=>'datetime',
        'completed_at'=>'datetime',
        'published_at'=>'datetime',
    ];

    public function getRouteKeyName(): string { return 'uuid'; }
    public function event() { return $this->belongsTo(Event::class); }
    public function group() { return $this->belongsTo(EventGroup::class, 'event_group_id'); }
    public function phase() { return $this->belongsTo(EventPhase::class, 'event_phase_id'); }
    public function rankingSnapshot() { return $this->belongsTo(EventRankingSnapshot::class, 'event_ranking_snapshot_id'); }
    public function matches() { return $this->hasMany(EventEliminationMatch::class)->orderBy('round_number')->orderBy('position'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
