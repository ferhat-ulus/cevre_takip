<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('module_user_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
            $table->unique(['user_id', 'module_id']);
        });

        $now = now();
        DB::table('modules')->insert([
            ['key' => 'environment', 'name' => 'Çevre Takip', 'description' => 'Çevre süreçleri, tesisler ve raporlama.', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'waste', 'name' => 'Atık Takip', 'description' => 'Atık yönetimi modülü.', 'is_active' => false, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'offers', 'name' => 'Teklif Takip', 'description' => 'Teklif yönetimi modülü.', 'is_active' => false, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $environmentModuleId = DB::table('modules')->where('key', 'environment')->value('id');
        DB::table('users')->where('role', 'employee')->where('active', true)->orderBy('id')->each(function (object $user) use ($environmentModuleId, $now) {
            DB::table('module_user_accesses')->insertOrIgnore([
                'user_id' => $user->id,
                'module_id' => $environmentModuleId,
                'role' => 'advisor',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_user_accesses');
        Schema::dropIfExists('modules');
    }
};
