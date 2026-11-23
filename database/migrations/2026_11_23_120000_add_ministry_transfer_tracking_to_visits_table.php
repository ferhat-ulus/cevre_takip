<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Existing rows stay untracked; new visits are explicitly marked pending.
            $table->string('ministry_transfer_status', 20)->default('untracked');
            $table->timestamp('ministry_entered_at')->nullable();
            $table->foreignId('ministry_entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('ministry_transfer_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['ministry_entered_by_user_id']);
            $table->dropColumn([
                'ministry_transfer_status',
                'ministry_entered_at',
                'ministry_entered_by_user_id',
                'ministry_transfer_snapshot',
            ]);
        });
    }
};
