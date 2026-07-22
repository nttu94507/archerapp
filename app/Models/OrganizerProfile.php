<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizerProfile extends Model
{
    protected $fillable = [
        'user_id','organization_name','organization_type','contact_name','contact_email','contact_phone',
        'website','social_link','registration_number','experience','planned_events','application_reason',
        'verification_document_path','status','approved_at','suspended_at','public_review_note',
    ];

    protected $casts = ['approved_at'=>'datetime','suspended_at'=>'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function applications(): HasMany { return $this->hasMany(OrganizerApplication::class); }
    public function reviewLogs(): HasMany { return $this->hasMany(OrganizerReviewLog::class)->latest(); }
    public function canCreateEvents(): bool { return $this->status === 'approved'; }
    public function canEditApplication(): bool { return in_array($this->status, ['draft','changes_requested','rejected','legacy_review'], true); }
}
