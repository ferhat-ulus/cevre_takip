<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firm_logs', function (Blueprint $table) {
            $table->string('from_consultant')->nullable();
            $table->string('to_consultant')->nullable();
            $table->text('reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('firm_logs', function (Blueprint $table) {
            $table->dropColumn(['from_consultant', 'to_consultant', 'reason']);
        });
    }
};
