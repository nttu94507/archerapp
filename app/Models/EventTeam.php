<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventTeam extends Model
{
    protected $fillable = ['uuid', 'event_id', 'event_group_id', 'captain_registration_id', 'name', 'team_format', 'is_open', 'recruitment_note', 'status', 'locked_at'];
    protected $casts = ['is_open'=>'boolean', 'locked_at'=>'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (EventTeam $team) => $team->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function event() { return $this->belongsTo(Event::class); }
    public function group() { return $this->belongsTo(EventGroup::class, 'event_group_id'); }
    public function captainRegistration() { return $this->belongsTo(EventRegistration::class, 'captain_registration_id'); }
    public function memberships() { return $this->hasMany(EventTeamMember::class); }
    public function activeMemberships() { return $this->hasMany(EventTeamMember::class)->where('status', 'active'); }
    public function competingMemberships() { return $this->hasMany(EventTeamMember::class)->where('status', 'active')->whereIn('role', ['captain','member']); }
    public function requiredSize(): int { return $this->team_format === 'mixed' ? 2 : 4; }
    public function scoringSize(): int { return $this->team_format === 'mixed' ? 2 : 3; }

    public function refreshStatus(): void
    {
        if (in_array($this->status, ['locked', 'disbanded'], true)) return;
        $this->update(['status'=>$this->activeMemberships()->count() >= $this->requiredSize() ? 'full' : 'recruiting']);
    }
}
