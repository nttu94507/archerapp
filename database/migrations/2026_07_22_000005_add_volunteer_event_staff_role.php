<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_staff')) return;

        Schema::table('event_staff', function (Blueprint $table): void {
            $table->enum('role', ['owner','manager','staff','volunteer','viewer'])->default('viewer')->change();
        });
    }

    public function down(): void
    {
        // Keep volunteer records valid on rollback.
    }
};
