<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan_code')->default('subscription');
            $table->string('status')->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('external_reference')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at'], 'org_sub_active_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_subscriptions');
    }
};
