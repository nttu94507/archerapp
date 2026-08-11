<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_staff')) {
            Schema::table('event_staff', function (Blueprint $table): void {
                $table->enum('role', ['owner','manager','staff','judge','chief_judge','volunteer','viewer'])->default('viewer')->change();
            });
        }

        if (Schema::hasTable('event_scoring_targets')) {
            Schema::table('event_scoring_targets', function (Blueprint $table): void {
                $table->string('judge_status', 20)->default('pending')->after('status');
                $table->text('judge_note')->nullable()->after('judge_status');
                $table->foreignId('reviewed_by')->nullable()->after('judge_note')->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                $table->foreignId('confirmed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable()->after('confirmed_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_scoring_targets')) {
            Schema::table('event_scoring_targets', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('confirmed_by');
                $table->dropConstrainedForeignId('reviewed_by');
                $table->dropColumn(['judge_status', 'judge_note', 'reviewed_at', 'confirmed_at']);
            });
        }
    }
};
