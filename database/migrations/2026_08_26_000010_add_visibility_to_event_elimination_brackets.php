<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_elimination_brackets', function (Blueprint $table): void {
            $table->string('visibility', 20)->default('internal')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_elimination_brackets', fn (Blueprint $table) => $table->dropColumn('visibility'));
    }
};
