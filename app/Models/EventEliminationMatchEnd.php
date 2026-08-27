<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventEliminationMatchEnd extends Model
{
    protected $fillable = [
        'event_elimination_match_id', 'end_number',
        'participant_one_arrows', 'participant_two_arrows',
        'participant_one_end_total', 'participant_two_end_total',
        'participant_one_running_total', 'participant_two_running_total', 'recorded_by',
    ];

    protected $casts = [
        'participant_one_arrows'=>'array',
        'participant_two_arrows'=>'array',
    ];

    public function match() { return $this->belongsTo(EventEliminationMatch::class, 'event_elimination_match_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
