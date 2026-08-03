<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_registrations')
            ->where('result_status', 'dnf')
            ->where(function ($query) {
                $query->where('status', 'checked_in')->orWhereNotNull('checked_in_at');
            })
            ->update(['result_status'=>'completed']);
    }

    public function down(): void
    {
        // This correction intentionally preserves reviewed result statuses.
    }
};
