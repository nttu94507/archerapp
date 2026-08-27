<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizerSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id', 'plan_code', 'status', 'starts_at', 'ends_at',
        'auto_renew', 'external_reference', 'activated_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activator()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $window) => $window
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->starts_at->lte(now())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
