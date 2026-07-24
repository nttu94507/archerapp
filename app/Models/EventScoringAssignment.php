<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventScoringAssignment extends Model
{
    protected $fillable = ['event_scoring_target_id', 'event_registration_id', 'position'];

    public function target() { return $this->belongsTo(EventScoringTarget::class, 'event_scoring_target_id'); }
    public function registration() { return $this->belongsTo(EventRegistration::class, 'event_registration_id'); }
}
