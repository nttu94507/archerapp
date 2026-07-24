<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerReviewLog extends Model
{
    protected $fillable = ['organizer_profile_id','organizer_application_id','actor_id','action','public_note','internal_note'];
    public function profile() { return $this->belongsTo(OrganizerProfile::class, 'organizer_profile_id'); }
    public function application() { return $this->belongsTo(OrganizerApplication::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
