<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventEliminationShootOff extends Model
{
    protected $fillable = [
        'event_elimination_match_id', 'attempt_number',
        'participant_one_arrow', 'participant_two_arrow',
        'participant_one_value', 'participant_two_value', 'status', 'decision_type',
        'winner_registration_id', 'decision_note', 'recorded_by', 'judged_by', 'judged_at',
    ];

    protected $casts = ['judged_at'=>'datetime'];

    public function match() { return $this->belongsTo(EventEliminationMatch::class, 'event_elimination_match_id'); }
    public function winner() { return $this->belongsTo(EventRegistration::class, 'winner_registration_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function judge() { return $this->belongsTo(User::class, 'judged_by'); }
}
