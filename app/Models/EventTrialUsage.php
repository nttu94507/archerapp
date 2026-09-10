<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTrialUsage extends Model
{
    protected $fillable = ['user_id', 'event_id', 'consumed_at'];

    protected function casts(): array
    {
        return ['consumed_at'=>'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function event() { return $this->belongsTo(Event::class); }
}
