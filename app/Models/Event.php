<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    //
    protected $fillable = [
        'name', 'date', 'mode', 'verified', 'level',
        'organizer', 'reg_start', 'reg_end',
        'venue', 'map_link', 'lat', 'lng',
    ];
    protected $casts = ['verified' => 'boolean', 'date' => 'date'];
}
