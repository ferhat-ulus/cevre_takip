<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_announcement_reads', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('announcement_key', 100);
            $table->timestamp('acknowledged_at');
            $table->primary(['user_id', 'announcement_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_announcement_reads');
    }
};
