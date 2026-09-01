<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_groups', function (Blueprint $table): void {
            $table->unsignedTinyInteger('team_size')->default(3)->after('is_team');
            $table->dateTime('team_formation_end')->nullable()->after('team_size');
        });

        Schema::create('event_teams', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('captain_registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('status', 20)->default('recruiting')->index();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['event_group_id', 'name'], 'evt_team_group_name_uq');
        });

        Schema::create('event_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['event_group_id', 'event_registration_id'], 'evt_team_group_registration_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team_members');
        Schema::dropIfExists('event_teams');
        Schema::table('event_groups', fn (Blueprint $table) => $table->dropColumn(['team_size', 'team_formation_end']));
    }
};
