<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArcheryShot extends Model
{
    //
    protected $fillable = [
        'session_id',
        'end_seq', 'shot_seq',
        'score', 'is_x', 'is_miss',
    ];

    protected $casts = [
        'end_seq'  => 'integer',
        'shot_seq' => 'integer',
        'score'    => 'integer',
        'is_x'     => 'boolean',
        'is_miss'  => 'boolean',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ArcherySession::class, 'session_id');
    }
}
