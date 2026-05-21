<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecondHandItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'seller_nickname',
        'photo_path',
        'description',
    ];
}
