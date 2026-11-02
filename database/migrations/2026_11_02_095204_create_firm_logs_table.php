<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firm_logs', function (Blueprint $table) {
            $table->id();
            $table->string('firm_id'); // Tesisin ID'si (string)
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // İşlemi yapan kullanıcı
            $table->string('action'); // Örn: 'consultant_changed'
            $table->text('description'); // Örn: "Danışman X yerine Y atandı"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firm_logs');
    }
};