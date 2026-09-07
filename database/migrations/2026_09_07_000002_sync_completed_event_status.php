<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'archived', 'completed'])
                ->default('draft')
                ->change();
        });

        DB::table('events')->whereNotNull('completed_at')->update(['status'=>'completed']);
    }

    public function down(): void
    {
        DB::table('events')->where('status', 'completed')->update(['status'=>'approved']);

        Schema::table('events', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'archived'])
                ->default('draft')
                ->change();
        });
    }
};
