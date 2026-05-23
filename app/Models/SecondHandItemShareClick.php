<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecondHandItemShareClick extends Model
{
    protected $fillable = [
        'second_hand_item_id',
        'sharer_id',
        'ref_code',
        'click_count',
    ];
}
