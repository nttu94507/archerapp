<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventStaff extends Model
{
    protected $table = 'event_staff';
    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'permissions',
        'status',
        'invited_at',
        'invited_by',
        'accepted_at',
    ];

    protected $casts = ['permissions' => 'array', 'invited_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function event() { return $this->belongsTo(Event::class); }
}
