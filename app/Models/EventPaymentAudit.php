<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPaymentAudit extends Model
{
    protected $fillable = ['event_registration_id', 'changed_by', 'from_status', 'to_status', 'amount', 'payment_method', 'payment_reference', 'note'];
}
