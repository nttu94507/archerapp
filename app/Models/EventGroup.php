<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventGroup extends Model
{
    //
    protected $fillable = [
        'event_id','name','bow_type','gender','age_class','distance',
        'quota','fee','is_team',
    ];

    protected $casts = [
        'is_team'   => 'boolean',
    ];

    public function event() {
        return $this->belongsTo(Event::class);
    }


}
