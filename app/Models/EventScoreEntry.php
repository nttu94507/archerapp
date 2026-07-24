<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventScoreEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_registration_id',
        'user_id',
        'end_number',
        'scores',
        'end_total',
    ];

    protected $casts = [
        'scores' => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function registration()
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }
}
