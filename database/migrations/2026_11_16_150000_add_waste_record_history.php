<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waste_imports', function (Blueprint $table) {
            $table->text('processor_address')->nullable();
        });
        Schema::create('waste_record_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('record_id')->index();
            $table->string('actor');
            $table->json('changes');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_record_history');
        Schema::table('waste_imports', fn (Blueprint $table) => $table->dropColumn('processor_address'));
    }
};
