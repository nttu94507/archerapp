<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    //
    protected $fillable = [
        'user_id',
        'phone', 'city',
        'emergency_contact_name', 'emergency_contact_phone',
        'birthdate', 'handedness', 'bow_type', 'club_name',
        'consent_signed_at', 'consent_version',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'consent_signed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
