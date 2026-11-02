<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->string('at');
            $table->string('who');
            $table->string('firm_id')->nullable();
            $table->string('firm')->nullable();
            $table->json('ch')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
