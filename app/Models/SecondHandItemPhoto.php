<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecondHandItemPhoto extends Model
{
    use HasFactory;

    protected $fillable = ['second_hand_item_id', 'photo_path', 'sort_order'];
}
