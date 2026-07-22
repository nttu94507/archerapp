<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizer_profiles')) {
            Schema::create('organizer_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('organization_name', 160);
                $table->string('organization_type', 40);
                $table->string('contact_name', 120);
                $table->string('contact_email');
                $table->string('contact_phone', 40);
                $table->string('website')->nullable();
                $table->string('social_link')->nullable();
                $table->string('registration_number', 80)->nullable();
                $table->text('experience')->nullable();
                $table->text('planned_events')->nullable();
                $table->text('application_reason');
                $table->string('verification_document_path')->nullable();
                $table->string('status', 40)->default('draft')->index();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->text('public_review_note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('organizer_applications')) {
            Schema::create('organizer_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->string('status', 40)->default('pending')->index();
                $table->json('snapshot');
                $table->timestamp('submitted_at');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique(['organizer_profile_id', 'version']);
            });
        }

        if (! Schema::hasTable('organizer_review_logs')) {
            Schema::create('organizer_review_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organizer_profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('organizer_application_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 40);
                $table->text('public_note')->nullable();
                $table->text('internal_note')->nullable();
                $table->timestamps();
                $table->index(['organizer_profile_id', 'created_at']);
            });
        }

        if (Schema::hasTable('event_staff')) {
            DB::table('event_staff')
                ->join('users', 'users.id', '=', 'event_staff.user_id')
                ->where('event_staff.role', 'owner')
                ->where('event_staff.status', 'active')
                ->select('users.id', 'users.name', 'users.email')
                ->distinct()
                ->orderBy('users.id')
                ->each(function (object $owner): void {
                    DB::table('organizer_profiles')->insertOrIgnore([
                        [
                            'user_id' => $owner->id,
                            'organization_name' => $owner->name,
                            'organization_type' => 'legacy',
                            'contact_name' => $owner->name,
                            'contact_email' => $owner->email,
                            'contact_phone' => '',
                            'application_reason' => '既有賽事主辦方，等待平台補充審核。',
                            'status' => 'legacy_review',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_review_logs');
        Schema::dropIfExists('organizer_applications');
        Schema::dropIfExists('organizer_profiles');
    }
};
