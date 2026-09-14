<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_staff')->where('role', 'chief_judge')->update(['role'=>'score_manager']);
    }

    public function down(): void
    {
        // The former role cannot be reconstructed reliably after conversion.
    }
};
