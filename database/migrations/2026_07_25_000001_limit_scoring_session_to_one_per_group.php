<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Existing installations may already contain multiple scoring sessions
         * for one group. Do not delete those sessions (and their target data)
         * automatically just to add a unique index. New sessions are serialized
         * by locking the event/group rows and checking for an existing session
         * inside the same transaction in EventScoringController.
         */
    }

    public function down(): void
    {
        //
    }
};
