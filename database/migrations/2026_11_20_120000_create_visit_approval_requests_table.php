<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('firm_id');
            $table->foreignId('assigned_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('visit_date');
            $table->string('title')->nullable();
            $table->text('notes');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'visit_date']);
            $table->index(['assigned_user_id', 'visit_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_approval_requests');
    }
};
