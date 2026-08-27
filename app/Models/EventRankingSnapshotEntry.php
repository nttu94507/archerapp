<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRankingSnapshotEntry extends Model
{
    protected $fillable = [
        'event_ranking_snapshot_id', 'event_registration_id', 'user_id',
        'rank_position', 'seed_position', 'total_score', 'ten_count', 'x_count',
        'result_status', 'is_eligible', 'tie_group', 'requires_tiebreak',
        'athlete_name', 'team_name',
    ];

    protected $casts = [
        'is_eligible'=>'boolean',
        'requires_tiebreak'=>'boolean',
    ];

    public function snapshot() { return $this->belongsTo(EventRankingSnapshot::class, 'event_ranking_snapshot_id'); }
    public function registration() { return $this->belongsTo(EventRegistration::class, 'event_registration_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
