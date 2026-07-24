<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventScoringTarget extends Model
{
    protected $fillable = [
        'event_scoring_session_id', 'target_number', 'access_token', 'status',
        'last_completed_end', 'last_synced_at',
    ];

    protected $casts = ['last_synced_at'=>'datetime'];

    public function session() { return $this->belongsTo(EventScoringSession::class, 'event_scoring_session_id'); }
    public function assignments() { return $this->hasMany(EventScoringAssignment::class)->orderBy('position'); }
}
