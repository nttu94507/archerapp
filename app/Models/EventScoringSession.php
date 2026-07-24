<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventScoringSession extends Model
{
    protected $fillable = [
        'event_id', 'event_group_id', 'name', 'total_arrows', 'arrows_per_end',
        'athletes_per_target', 'status', 'started_at', 'completed_at', 'created_by',
    ];

    protected $casts = ['started_at'=>'datetime', 'completed_at'=>'datetime'];

    public function event() { return $this->belongsTo(Event::class); }
    public function group() { return $this->belongsTo(EventGroup::class, 'event_group_id'); }
    public function targets() { return $this->hasMany(EventScoringTarget::class); }

    public function totalEnds(): int
    {
        return (int) ceil($this->total_arrows / max(1, $this->arrows_per_end));
    }
}
