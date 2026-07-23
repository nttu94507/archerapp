<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_badges', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_badges','issuer_type')) $table->string('issuer_type',20)->default('organizer')->after('created_by');
            if (! Schema::hasColumn('event_badges','issuer_name')) $table->string('issuer_name',120)->nullable()->after('issuer_type');
            if (! Schema::hasColumn('event_badges','external_activity_name')) $table->string('external_activity_name',160)->nullable()->after('issuer_name');
            if (! Schema::hasColumn('event_badges','external_activity_date')) $table->date('external_activity_date')->nullable()->after('external_activity_name');
            if (! Schema::hasColumn('event_badges','external_activity_location')) $table->string('external_activity_location')->nullable()->after('external_activity_date');
            if (! Schema::hasColumn('event_badges','max_supply')) $table->unsignedInteger('max_supply')->nullable()->after('is_active');
        });
        Schema::table('event_badges', fn (Blueprint $table) => $table->foreignId('event_id')->nullable()->change());

        if (! Schema::hasTable('badge_campaigns')) {
            Schema::create('badge_campaigns', function (Blueprint $table): void {
                $table->id(); $table->foreignId('event_badge_id')->constrained()->cascadeOnDelete();
                $table->string('name',160); $table->string('distribution_method',30)->default('manual');
                $table->timestamp('starts_at')->nullable(); $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true); $table->timestamps();
            });
        }

        Schema::table('user_event_badges', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_event_badges','public_id')) $table->uuid('public_id')->nullable()->unique()->after('id');
            if (! Schema::hasColumn('user_event_badges','badge_campaign_id')) $table->foreignId('badge_campaign_id')->nullable()->after('event_badge_claim_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('user_event_badges','limited_serial')) $table->unsignedInteger('limited_serial')->nullable()->after('award_source');
            if (! Schema::hasColumn('user_event_badges','issuer_name_snapshot')) $table->string('issuer_name_snapshot',120)->nullable()->after('award_note');
            if (! Schema::hasColumn('user_event_badges','event_name_snapshot')) $table->string('event_name_snapshot',160)->nullable()->after('issuer_name_snapshot');
            if (! Schema::hasColumn('user_event_badges','group_name_snapshot')) $table->string('group_name_snapshot',120)->nullable()->after('event_name_snapshot');
            if (! Schema::hasColumn('user_event_badges','criteria_snapshot')) $table->string('criteria_snapshot')->nullable()->after('group_name_snapshot');
            if (! Schema::hasColumn('user_event_badges','placement_snapshot')) $table->unsignedTinyInteger('placement_snapshot')->nullable()->after('criteria_snapshot');
            if (! Schema::hasColumn('user_event_badges','score_snapshot')) $table->unsignedInteger('score_snapshot')->nullable()->after('placement_snapshot');
        });

        DB::table('event_badges')->orderBy('id')->eachById(function (object $badge): void {
            $event = $badge->event_id ? DB::table('events')->find($badge->event_id) : null;
            DB::table('event_badges')->where('id',$badge->id)->update(['issuer_name'=>$badge->issuer_name ?: ($event->organizer ?? 'ArrowTrack')]);
        });
        DB::table('user_event_badges')->whereNull('public_id')->orderBy('id')->eachById(function (object $award): void {
            $badge=DB::table('event_badges')->find($award->event_badge_id); $event=$badge?->event_id ? DB::table('events')->find($badge->event_id) : null;
            DB::table('user_event_badges')->where('id',$award->id)->update(['public_id'=>(string)Str::uuid(),'issuer_name_snapshot'=>$badge?->issuer_name ?? $event?->organizer ?? 'ArrowTrack','event_name_snapshot'=>$event?->name ?? $badge?->external_activity_name]);
        });
    }

    public function down(): void {}
};
