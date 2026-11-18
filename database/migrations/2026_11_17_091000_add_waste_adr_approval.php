<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_imports', function (Blueprint $table) {
            $table->timestamp('adr_approved_at')->nullable();
            $table->foreignId('adr_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('waste_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adr_approved_by');
            $table->dropColumn('adr_approved_at');
        });
    }
};
