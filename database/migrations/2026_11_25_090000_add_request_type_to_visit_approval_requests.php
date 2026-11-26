<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_approval_requests', function (Blueprint $table) {
            $table->string('request_type', 20)->default('create')->after('visit_id');
        });
    }

    public function down(): void
    {
        Schema::table('visit_approval_requests', function (Blueprint $table) {
            $table->dropColumn('request_type');
        });
    }
};
