<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_badges', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_badges', 'award_rule')) $table->string('award_rule', 30)->default('manual')->after('eligibility');
            if (! Schema::hasColumn('event_badges', 'event_group_id')) $table->foreignId('event_group_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('event_badges', 'placement')) $table->unsignedTinyInteger('placement')->nullable()->after('award_rule');
        });

        Schema::table('event_registrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_registrations', 'payment_status')) $table->string('payment_status', 30)->default('pending')->after('paid');
            if (! Schema::hasColumn('event_registrations', 'payment_confirmed_at')) $table->timestamp('payment_confirmed_at')->nullable()->after('payment_status');
            if (! Schema::hasColumn('event_registrations', 'payment_confirmed_by')) $table->foreignId('payment_confirmed_by')->nullable()->after('payment_confirmed_at')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('event_registrations', 'payment_amount')) $table->decimal('payment_amount', 10, 2)->nullable()->after('payment_confirmed_by');
            if (! Schema::hasColumn('event_registrations', 'payment_method')) $table->string('payment_method', 30)->nullable()->after('payment_amount');
            if (! Schema::hasColumn('event_registrations', 'payment_reference')) $table->string('payment_reference', 120)->nullable()->after('payment_method');
            if (! Schema::hasColumn('event_registrations', 'payment_note')) $table->text('payment_note')->nullable()->after('payment_reference');
        });

        if (! Schema::hasTable('event_payment_audits')) {
            Schema::create('event_payment_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_registration_id')->constrained()->cascadeOnDelete();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->decimal('amount', 10, 2)->nullable();
                $table->string('payment_method', 30)->nullable();
                $table->string('payment_reference', 120)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('user_event_badges', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_event_badges', 'award_source')) $table->string('award_source', 30)->default('manual')->after('awarded_at');
            if (! Schema::hasColumn('user_event_badges', 'award_note')) $table->text('award_note')->nullable()->after('award_source');
        });

        // Preserve the meaning of the existing paid flag.
        \DB::table('event_registrations')->where('paid', true)->where('payment_status', 'pending')->update(['payment_status' => 'paid']);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payment_audits');
        // Columns are intentionally retained to keep rollback safe on installations
        // that may already have introduced equivalent fields.
    }
};
