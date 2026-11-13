<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_holidays', function (Blueprint $table) {
            $table->dropUnique(['holiday_date']);
            $table->unique(['holiday_date', 'title']);
        });
    }

    public function down(): void
    {
        Schema::table('calendar_holidays', function (Blueprint $table) {
            $table->dropUnique(['holiday_date', 'title']);
            $table->unique('holiday_date');
        });
    }
};
