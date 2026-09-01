<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTeamMember extends Model
{
    protected $fillable = ['event_team_id', 'event_group_id', 'event_registration_id', 'role', 'status', 'requested_by', 'responded_at'];
    protected $casts = ['responded_at'=>'datetime'];

    public function team() { return $this->belongsTo(EventTeam::class, 'event_team_id'); }
    public function registration() { return $this->belongsTo(EventRegistration::class, 'event_registration_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
}
