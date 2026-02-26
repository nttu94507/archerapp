<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
