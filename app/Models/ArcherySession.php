<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArcherySession extends Model
{
    //
    protected $fillable = [
        'user_id',
        'bow_type', 'venue',
        'distance_m',
        'arrows_total', 'arrows_per_end', 'target_face',
        'score_total', 'x_count', 'm_count',
        'note',
    ];

    protected $casts = [
        'distance_m'     => 'integer',
        'arrows_total'   => 'integer',
        'arrows_per_end' => 'integer',
        'score_total'    => 'integer',
        'x_count'        => 'integer',
        'm_count'        => 'integer',
        'target_face'    => 'string',
    ];

    public function shots(): HasMany
    {
        return $this->hasMany(ArcheryShot::class, 'session_id');
    }

    /* ---------- 常用便利方法（選用） ---------- */

    // 取得「第 N 趟」的所有箭（1-based）
    public function shotsOfEnd(int $endSeq)
    {
        return $this->shots()->where('end_seq', $endSeq)->orderBy('shot_seq');
    }

    // 每趟合計（以集合回傳）
    public function endSums()
    {
        return $this->shots()
            ->selectRaw('end_seq, SUM(score) as end_sum')
            ->groupBy('end_seq')
            ->orderBy('end_seq')
            ->get();
    }
}
