<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;
    //
    protected $fillable = ['archer_id','elo','last_played_at'];
    protected $casts = ['last_played_at' => 'datetime'];
    public $timestamps = false;
}
