<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_badges', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_badges','location_claim_enabled')) $table->boolean('location_claim_enabled')->default(false)->after('claim_enabled');
            if (! Schema::hasColumn('event_badges','claim_lat')) $table->decimal('claim_lat',10,7)->nullable()->after('location_claim_enabled');
            if (! Schema::hasColumn('event_badges','claim_lng')) $table->decimal('claim_lng',10,7)->nullable()->after('claim_lat');
            if (! Schema::hasColumn('event_badges','claim_radius_km')) $table->decimal('claim_radius_km',6,2)->default(10)->after('claim_lng');
        });
    }

    public function down(): void {}
};
