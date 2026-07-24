<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventAuditLog extends Model
{
    protected $fillable = ['event_id', 'user_id', 'action', 'subject_type', 'subject_id', 'metadata'];
    protected $casts = ['metadata' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
}
