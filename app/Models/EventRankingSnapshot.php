<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventRankingSnapshot extends Model
{
    protected static function booted(): void
    {
        static::creating(function (EventRankingSnapshot $snapshot): void {
            $snapshot->uuid ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'uuid', 'event_id', 'event_group_id', 'event_phase_id', 'version',
        'status', 'source_hash', 'ranking_rule', 'locked_at', 'superseded_at', 'created_by',
    ];

    protected $casts = [
        'ranking_rule'=>'array',
        'locked_at'=>'datetime',
        'superseded_at'=>'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event() { return $this->belongsTo(Event::class); }
    public function group() { return $this->belongsTo(EventGroup::class, 'event_group_id'); }
    public function phase() { return $this->belongsTo(EventPhase::class, 'event_phase_id'); }
    public function entries() { return $this->hasMany(EventRankingSnapshotEntry::class)->orderByRaw('seed_position IS NULL, seed_position')->orderBy('id'); }
    public function eliminationBracket() { return $this->hasOne(EventEliminationBracket::class, 'event_ranking_snapshot_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
