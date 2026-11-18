<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('waste_imports')) return;
        Schema::create('waste_imports', function (Blueprint $table) {
            $table->id();
            $table->string('waste_date')->index();
            foreach ([
                'waste_name', 'waste_code', 'tkn', 'tdn', 'plate', 'producer_name', 'processor_name',
                'district', 'processor_vkn', 'producer_vkn', 'un_number', 'adr_name', 'adr_class',
                'observed_amount', 'address', 'period',
            ] as $column) {
                $table->text($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        // The table can predate this migration and may contain imported records.
    }
};
