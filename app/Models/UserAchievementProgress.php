<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAchievementProgress extends Model
{
    protected $table = 'user_achievement_progress';

    protected $fillable = [
        'user_id',
        'achievement_definition_id',
        'current_value',
        'target_value',
        'progress_percent',
        'unlocked_at',
        'last_calculated_at',
    ];

    protected $casts = [
        'current_value' => 'integer',
        'target_value' => 'integer',
        'progress_percent' => 'integer',
        'unlocked_at' => 'datetime',
        'last_calculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AchievementDefinition::class, 'achievement_definition_id');
    }
}
