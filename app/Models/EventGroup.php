<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id','name','bow_type','gender','age_class','distance','arrow_count',
        'quota','fee','is_team','target_slots','registration_closed',
    ];

    protected $casts = [
        'is_team'   => 'boolean',
        'registration_closed' => 'boolean',
    ];

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function registrations()
    {
        // 如果外鍵是 event_group_id
        return $this->hasMany(EventRegistration::class, 'event_group_id', 'id');

        // 若你的外鍵其實叫 group_id，請改成：
        // return $this->hasMany(Registration::class, 'group_id', 'id');
    }

}
