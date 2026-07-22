<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumnUnlessExists('events', 'cancelled_at', fn (Blueprint $table) => $table->timestamp('cancelled_at')->nullable()->after('published_at'));
        $this->addColumnUnlessExists('events', 'completed_at', fn (Blueprint $table) => $table->timestamp('completed_at')->nullable()->after('cancelled_at'));
        $this->addColumnUnlessExists('events', 'review_note', fn (Blueprint $table) => $table->text('review_note')->nullable()->after('completed_at'));

        $this->addColumnUnlessExists('event_groups', 'reg_start', fn (Blueprint $table) => $table->dateTime('reg_start')->nullable()->after('is_team'));
        $this->addColumnUnlessExists('event_groups', 'reg_end', fn (Blueprint $table) => $table->dateTime('reg_end')->nullable()->after('reg_start'));
        $this->addColumnUnlessExists('event_groups', 'arrows_per_end', fn (Blueprint $table) => $table->unsignedTinyInteger('arrows_per_end')->default(6)->after('arrow_count'));

        $this->addColumnUnlessExists('event_staff', 'permissions', fn (Blueprint $table) => $table->json('permissions')->nullable()->after('role'));

        $this->addColumnUnlessExists('event_registrations', 'checked_in_at', fn (Blueprint $table) => $table->timestamp('checked_in_at')->nullable()->after('status'));
        $this->addColumnUnlessExists('event_registrations', 'checked_in_by', fn (Blueprint $table) => $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete());
        $this->addColumnUnlessExists('event_registrations', 'score_verified_at', fn (Blueprint $table) => $table->timestamp('score_verified_at')->nullable()->after('score_submitted_at'));
        $this->addColumnUnlessExists('event_registrations', 'score_verified_by', fn (Blueprint $table) => $table->foreignId('score_verified_by')->nullable()->after('score_verified_at')->constrained('users')->nullOnDelete());
        $this->addColumnUnlessExists('event_registrations', 'result_published_at', fn (Blueprint $table) => $table->timestamp('result_published_at')->nullable()->after('score_verified_by'));

        $this->addColumnUnlessExists('event_score_entries', 'event_registration_id', fn (Blueprint $table) => $table->foreignId('event_registration_id')->nullable()->after('event_id')->constrained('event_registrations')->cascadeOnDelete());

        DB::table('event_score_entries')->orderBy('id')->eachById(function (object $entry): void {
            $registrationId = DB::table('event_registrations')
                ->where('event_id', $entry->event_id)
                ->where('user_id', $entry->user_id)
                ->orderBy('id')
                ->value('id');

            if ($registrationId) {
                DB::table('event_score_entries')->where('id', $entry->id)->update(['event_registration_id' => $registrationId]);
            }
        });

        if (! $this->hasIndex('event_score_entries', 'event_score_entries_event_id_index')) {
            Schema::table('event_score_entries', fn (Blueprint $table) => $table->index('event_id'));
        }
        if ($this->hasIndex('event_score_entries', 'event_score_entries_event_id_user_id_end_number_unique')) {
            Schema::table('event_score_entries', fn (Blueprint $table) => $table->dropUnique('event_score_entries_event_id_user_id_end_number_unique'));
        }
        if (! $this->hasIndex('event_score_entries', 'event_registration_end_unique')) {
            Schema::table('event_score_entries', fn (Blueprint $table) => $table->unique(['event_registration_id', 'end_number'], 'event_registration_end_unique'));
        }

        if (! Schema::hasTable('event_audit_logs')) {
            Schema::create('event_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action', 80);
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['event_id', 'created_at']);
            });
        }

        DB::table('events')->where('verified', true)->where('status', 'pending')->update([
            'status' => 'approved',
            'published_at' => DB::raw('COALESCE(published_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        // This migration may adopt columns that already existed in older deployments.
        // Only roll back objects that are unambiguously owned by this migration.
        Schema::dropIfExists('event_audit_logs');
        if ($this->hasIndex('event_score_entries', 'event_registration_end_unique')) {
            Schema::table('event_score_entries', fn (Blueprint $table) => $table->dropUnique('event_registration_end_unique'));
        }
        if (! $this->hasIndex('event_score_entries', 'event_score_entries_event_id_user_id_end_number_unique')) {
            Schema::table('event_score_entries', fn (Blueprint $table) => $table->unique(['event_id', 'user_id', 'end_number'])) ;
        }
    }

    private function addColumnUnlessExists(string $tableName, string $columnName, callable $definition): void
    {
        if (! Schema::hasColumn($tableName, $columnName)) {
            Schema::table($tableName, $definition);
        }
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        return collect(Schema::getIndexes($tableName))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $indexName);
    }
};
