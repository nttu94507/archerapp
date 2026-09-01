<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventEliminationMatch extends Model
{
    protected static function booted(): void
    {
        static::creating(function (EventEliminationMatch $match): void {
            $match->uuid ??= (string) Str::uuid();
            $match->access_token ??= (string) Str::uuid();
            $match->device_pin ??= (string) random_int(100000, 999999);
        });
    }

    protected $fillable = [
        'uuid', 'access_token', 'device_pin', 'device_token_hash', 'device_bound_at',
        'device_last_seen_at', 'device_user_agent', 'event_elimination_bracket_id', 'round_number', 'position', 'match_type',
        'label', 'status', 'participant_one_snapshot_entry_id', 'participant_two_snapshot_entry_id',
        'participant_one_registration_id', 'participant_two_registration_id',
        'participant_one_team_id', 'participant_two_team_id',
        'participant_one_seed', 'participant_two_seed', 'participant_one_set_points',
        'participant_two_set_points', 'current_set', 'participant_one_total',
        'participant_two_total', 'current_end', 'next_match_id', 'next_slot',
        'loser_next_match_id', 'loser_next_slot', 'winner_registration_id',
        'loser_registration_id', 'winner_team_id', 'loser_team_id', 'target_number', 'scheduled_at', 'completed_at',
    ];

    protected $casts = ['scheduled_at'=>'datetime', 'completed_at'=>'datetime', 'device_bound_at'=>'datetime', 'device_last_seen_at'=>'datetime'];

    public function getRouteKeyName(): string { return 'uuid'; }
    public function bracket() { return $this->belongsTo(EventEliminationBracket::class, 'event_elimination_bracket_id'); }
    public function participantOneEntry() { return $this->belongsTo(EventRankingSnapshotEntry::class, 'participant_one_snapshot_entry_id'); }
    public function participantTwoEntry() { return $this->belongsTo(EventRankingSnapshotEntry::class, 'participant_two_snapshot_entry_id'); }
    public function participantOneRegistration() { return $this->belongsTo(EventRegistration::class, 'participant_one_registration_id'); }
    public function participantTwoRegistration() { return $this->belongsTo(EventRegistration::class, 'participant_two_registration_id'); }
    public function participantOneTeam() { return $this->belongsTo(EventTeam::class, 'participant_one_team_id'); }
    public function participantTwoTeam() { return $this->belongsTo(EventTeam::class, 'participant_two_team_id'); }
    public function nextMatch() { return $this->belongsTo(self::class, 'next_match_id'); }
    public function loserNextMatch() { return $this->belongsTo(self::class, 'loser_next_match_id'); }
    public function sets() { return $this->hasMany(EventEliminationMatchSet::class, 'event_elimination_match_id')->orderBy('set_number'); }
    public function ends() { return $this->hasMany(EventEliminationMatchEnd::class, 'event_elimination_match_id')->orderBy('end_number'); }
    public function shootOffs() { return $this->hasMany(EventEliminationShootOff::class, 'event_elimination_match_id')->orderBy('attempt_number'); }
}
