<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AchievementDefinition extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'condition_type',
        'target_value',
        'points',
        'is_active',
    ];

    protected $casts = [
        'target_value' => 'integer',
        'points' => 'integer',
        'is_active' => 'boolean',
    ];

    public function progressRecords(): HasMany
    {
        return $this->hasMany(UserAchievementProgress::class, 'achievement_definition_id');
    }
}
