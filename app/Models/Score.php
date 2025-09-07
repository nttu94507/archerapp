<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    use HasFactory;
    //
    protected $fillable = [
        'archer_id','event_id','round_id',
        'total_score','x_count','ten_count','arrow_count',
        'stdev','scored_at','arrows'
    ];

    protected $casts = [
        'arrows'   => 'array',
        'scored_at'=> 'datetime',
    ];

    public function archer(){
        return $this->belongsTo(Archer::class);
    }
    public function event(){
        return $this->belongsTo(Event::class);
    }
    public function round(){
        return $this->belongsTo(Round::class);
    }
}
