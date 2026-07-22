<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    //
    protected $fillable = [
        'name', 'start_date','end_date', 'mode', 'verified', 'level',
        'organizer', 'reg_start', 'reg_end',
        'venue', 'map_link', 'lat', 'lng', 'status', 'published_at',
        'cancelled_at', 'completed_at', 'review_note',
    ];
    protected $casts = [
        'verified' => 'boolean',
        'start_date' => 'date', 'end_date' => 'date',
        'reg_start' => 'datetime', 'reg_end' => 'datetime',
        'published_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    // app/Models/Event.php
    public function groups() {
        return $this->hasMany(EventGroup::class);
    }

    public function staff() {
        return $this->hasMany(EventStaff::class);
    }

    public function badges() {
        return $this->hasMany(EventBadge::class);
    }

    public function registrations() {
        return $this->hasMany(EventRegistration::class);
    }

    public function auditLogs() {
        return $this->hasMany(EventAuditLog::class)->latest();
    }

    public function scopePublished($query) {
        return $query->where('status', 'approved')->whereNotNull('published_at')->whereNull('cancelled_at');
    }

    public function isPublished(): bool {
        return $this->status === 'approved' && $this->published_at !== null && $this->cancelled_at === null;
    }
}
