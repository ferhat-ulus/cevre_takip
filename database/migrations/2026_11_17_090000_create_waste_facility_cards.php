<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_companies', function (Blueprint $table) {
            $table->id();
            $table->string('vkn', 10)->unique();
            $table->string('name', 500);
        });
        Schema::create('waste_facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('waste_companies');
            $table->string('role', 16);
            $table->string('name', 500);
            $table->text('address')->nullable();
            $table->string('district')->nullable();
            $table->string('ckn', 20)->nullable();
            $table->unique(['role', 'name']);
        });
        Schema::create('waste_facility_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained('waste_facilities');
            $table->string('role', 16);
            $table->string('fingerprint', 64);
            $table->unique(['role', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_facility_aliases');
        Schema::dropIfExists('waste_facilities');
        Schema::dropIfExists('waste_companies');
    }
};
