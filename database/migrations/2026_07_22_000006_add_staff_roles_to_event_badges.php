<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('event_badges', 'staff_roles')) {
            Schema::table('event_badges', fn (Blueprint $table) => $table->json('staff_roles')->nullable()->after('award_rule'));
        }
    }

    public function down(): void
    {
        // Retained for safe rollback on databases that may share this field.
    }
};
