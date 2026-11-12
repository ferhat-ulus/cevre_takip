<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            // SQLite, geliştirme ortamında sütun eklerken tabloyu yeniden
            // oluşturur; foreign key tanımı mevcut tesis alanlarını kaybetme
            // riski doğurur. Kimlik doğrulaması uygulama katmanında yapılır.
            $table->unsignedBigInteger('consultant_user_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('firms', function (Blueprint $table) {
            $table->dropIndex(['consultant_user_id']);
            $table->dropColumn('consultant_user_id');
        });
    }
};
