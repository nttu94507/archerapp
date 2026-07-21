<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecondHandItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'price',
        'seller_id',
        'photo_path',
        'description',
        'contact_type',
        'contact_value',
        'is_sold',
        'view_count',
    ];

    protected $casts = [
        'is_sold' => 'boolean',
        'view_count' => 'integer',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SecondHandItemPhoto::class)->orderBy('sort_order');
    }

    public function getSellerDisplayNameAttribute(): string
    {
        if (! $this->seller) {
            return '未知賣家';
        }

        $nickname = trim((string) $this->seller->nickname);

        return $nickname !== '' ? $nickname : $this->seller->name;
    }
}
