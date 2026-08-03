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
        'paid','payment_status','payment_confirmed_at','payment_confirmed_by','payment_amount','payment_method','payment_reference','payment_note','score_submitted_at','checked_in_at','checked_in_by',
        'score_verified_at','score_verified_by','result_published_at','result_status',
    ];

    protected $casts = [
        'paid'         => 'boolean',
        'payment_confirmed_at' => 'datetime',
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

    public function scoringAssignment()
    {
        return $this->hasOne(EventScoringAssignment::class, 'event_registration_id');
    }
}
