<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    //
    protected $fillable = ['name', 'date', 'mode', 'verified', 'level'];
    protected $casts = ['verified' => 'boolean', 'date' => 'date'];
}
