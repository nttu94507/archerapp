<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerApplication extends Model
{
    protected $fillable = ['organizer_profile_id','version','status','snapshot','submitted_at','reviewed_by','reviewed_at'];
    protected $casts = ['snapshot'=>'array','submitted_at'=>'datetime','reviewed_at'=>'datetime'];
    public function profile() { return $this->belongsTo(OrganizerProfile::class, 'organizer_profile_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
