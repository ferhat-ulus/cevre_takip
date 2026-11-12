<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_user_accesses')
            ->where('role', 'module_admin')
            ->update(['role' => 'advisor', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Modül yöneticisi rolü geri getirilmeyeceği için ters dönüşüm yapılmaz.
    }
};
