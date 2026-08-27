<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('plan_code', 30)->default('free')->after('uuid')->index();
            $table->string('plan_status', 30)->default('active')->after('plan_code')->index();
            $table->json('plan_limits_snapshot')->nullable()->after('plan_status');
            $table->json('plan_features_snapshot')->nullable()->after('plan_limits_snapshot');
            $table->timestamp('plan_activated_at')->nullable()->after('plan_features_snapshot');
            $table->timestamp('plan_expires_at')->nullable()->after('plan_activated_at');
            $table->string('plan_order_reference', 120)->nullable()->after('plan_expires_at');
        });

        // Existing events keep every current capability. New events are initialized as free by the model.
        DB::table('events')->update([
            'plan_code'=>'legacy',
            'plan_status'=>'active',
            'plan_limits_snapshot'=>json_encode([
                'active_events'=>null, 'groups'=>null, 'staff_members'=>null,
                'athletes'=>null, 'targets'=>null, 'arrows_per_phase'=>null, 'badges'=>null,
            ], JSON_THROW_ON_ERROR),
            'plan_features_snapshot'=>json_encode([
                'qualification'=>true, 'individual_elimination'=>true,
                'multiple_groups'=>true, 'multiple_rounds'=>true, 'live_results'=>true,
                'internal_visibility'=>true, 'public_visibility'=>true,
                'advanced_judging'=>true, 'score_audit_log'=>true, 'data_export'=>true,
                'advanced_badges'=>true, 'custom_branding'=>true,
            ], JSON_THROW_ON_ERROR),
            'plan_activated_at'=>now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['plan_code']);
            $table->dropIndex(['plan_status']);
            $table->dropColumn([
                'plan_code', 'plan_status', 'plan_limits_snapshot', 'plan_features_snapshot',
                'plan_activated_at', 'plan_expires_at', 'plan_order_reference',
            ]);
        });
    }
};
