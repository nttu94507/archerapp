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
        'paid','score_submitted_at','checked_in_at','checked_in_by',
        'score_verified_at','score_verified_by','result_published_at',
    ];

    protected $casts = [
        'paid'         => 'boolean',
        'withdrawn_at' => 'datetime',
        'score_submitted_at' => 'datetime',
        'checked_in_at' => 'datetime', 'score_verified_at' => 'datetime', 'result_published_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function event_group(){
        return $this->belongsTo(EventGroup::class, 'event_group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scoreEntries()
    {
        return $this->hasMany(EventScoreEntry::class);
    }
}
