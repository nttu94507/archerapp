<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('second_hand_item_share_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('second_hand_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sharer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ref_code', 64)->unique();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->unique(['second_hand_item_id', 'sharer_id'], 'shi_share_click_item_sharer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('second_hand_item_share_clicks');
    }
};
