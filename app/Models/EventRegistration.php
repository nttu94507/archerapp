<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    //
    protected $fillable = [
        'event_id','event_group_id','user_id',
        'name','email','phone','team_name',
        'status','withdraw_reason','withdrawn_at','withdrawn_by',
        'paid','score_submitted_at','target_number','target_letter',
    ];

    protected $casts = [
        'paid'         => 'boolean',
        'withdrawn_at' => 'datetime',
        'score_submitted_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function event_group(){
        return $this->belongsTo(EventGroup::class, 'event_group_id');
    }
}
