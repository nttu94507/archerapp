<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'google_id',
        'google_avatar',
        'email_verified_at',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }


    public function archerySessions(): HasMany
    {
        return $this->hasMany(ArcherySession::class);
    }

    public function achievementProgress(): HasMany
    {
        return $this->hasMany(UserAchievementProgress::class);
    }

    public function eventBadgeClaims(): HasMany
    {
        return $this->hasMany(EventBadgeClaim::class);
    }

    public function eventBadges(): HasMany
    {
        return $this->hasMany(UserEventBadge::class);
    }

    public function organizerProfile(): HasOne
    {
        return $this->hasOne(OrganizerProfile::class);
    }

    public function organizerSubscription(): HasOne
    {
        return $this->hasOne(OrganizerSubscription::class);
    }

    public function activeOrganizerSubscription(): ?OrganizerSubscription
    {
        return $this->organizerSubscription()->active()->first();
    }

    public function hasActiveOrganizerSubscription(): bool
    {
        return $this->organizerSubscription()->active()->exists();
    }

    public function canCreateEvents(): bool
    {
        return $this->isAdmin() || $this->organizerProfile()->where('status', 'approved')->exists();
    }

    public function hasCompletedProfile(): bool
    {
        return !is_null($this->profile_completed_at);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->nickname ?: $this->name;
    }
}
