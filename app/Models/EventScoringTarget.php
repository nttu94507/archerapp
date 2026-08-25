<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventScoringTarget extends Model
{
    protected $fillable = [
        'event_scoring_session_id', 'target_number', 'access_token', 'status',
        'judge_status', 'judge_note', 'reviewed_by', 'reviewed_at', 'confirmed_by', 'confirmed_at',
        'last_completed_end', 'first_round_completed_at', 'second_round_started_at',
        'last_synced_at', 'device_pin', 'device_token_hash',
        'device_bound_at', 'device_last_seen_at', 'device_user_agent',
    ];

    protected $casts = [
        'last_synced_at'=>'datetime',
        'first_round_completed_at'=>'datetime',
        'second_round_started_at'=>'datetime',
        'reviewed_at'=>'datetime',
        'confirmed_at'=>'datetime',
        'device_bound_at'=>'datetime',
        'device_last_seen_at'=>'datetime',
    ];

    public function session() { return $this->belongsTo(EventScoringSession::class, 'event_scoring_session_id'); }
    public function assignments() { return $this->hasMany(EventScoringAssignment::class)->orderBy('position'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function confirmer() { return $this->belongsTo(User::class, 'confirmed_by'); }
}
