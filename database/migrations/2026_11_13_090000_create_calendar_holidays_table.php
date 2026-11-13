<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('title');
            $table->string('type')->default('religious');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('calendar_holidays')->insert([
            ['holiday_date' => '2026-03-20', 'title' => 'Ramazan Bayramı 1. Gün', 'type' => 'religious', 'created_at' => $now, 'updated_at' => $now],
            ['holiday_date' => '2026-03-21', 'title' => 'Ramazan Bayramı 2. Gün', 'type' => 'religious', 'created_at' => $now, 'updated_at' => $now],
            ['holiday_date' => '2026-03-22', 'title' => 'Ramazan Bayramı 3. Gün', 'type' => 'religious', 'created_at' => $now, 'updated_at' => $now],
            ['holiday_date' => '2026-05-27', 'title' => 'Kurban Bayramı 1. Gün', 'type' => 'religious', 'created_at' => $now, 'updated_at' => $now],
            ['holiday_date' => '2026-05-28', 'title' => 'Kurban Bayramı 2. Gün', 'type' => 'religious', 'created_at' => $now, 'updated_at' => $now],
            ['holiday_date' => '2026-05-29', 'title' => 'Kurban Bayramı 3. Gün', 'type' => 'religious', 'created_at' => $now, 'updated_at' => $now],
            ['holiday_date' => '2026-05-30', 'title' => 'Kurban Bayramı 4. Gün', 'type' => 'religious', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_holidays');
    }
};
