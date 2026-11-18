<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportWasteReleaseData extends Command
{
    protected $signature = 'waste:import-release-data {file : Prototipten alınan veri dosyası} {--apply : Veriyi boş Atık Takip tablolarına yaz}';
    protected $description = 'Atık Takip veri paketini kontrol eder ve boş modüle aktarır.';

    private const TABLES = [
        'waste_companies', 'waste_facilities', 'waste_facility_aliases', 'waste_imports', 'waste_record_history',
    ];

    public function handle(): int
    {
        $file = $this->argument('file');
        $contents = @file_get_contents($file);
        if ($contents === false) {
            $this->error('Veri dosyası okunamadı.');
            return self::FAILURE;
        }
        $bundle = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        $tables = $bundle['tables'] ?? [];
        if (($bundle['version'] ?? null) !== 1 || array_keys($tables) !== self::TABLES || !$tables['waste_imports']) {
            $this->error('Atık Takip veri paketi biçimi geçersiz.');
            return self::FAILURE;
        }
        $counts = array_map('count', $tables);
        $this->info(json_encode($counts, JSON_THROW_ON_ERROR));
        if (!$this->option('apply')) return self::SUCCESS;

        DB::transaction(function () use ($tables, $counts) {
            $module = DB::table('modules')->where('key', 'waste')->lockForUpdate()->first();
            if (!$module || $module->is_active) throw new RuntimeException('Aktarımdan önce Atık Takip modülü kapalı olmalıdır.');
            foreach (self::TABLES as $table) {
                if (DB::table($table)->exists()) throw new RuntimeException('Aktarım durdu. Atık Takip hedef tablosu boş değil: '.$table);
            }
            foreach ($tables as $table => $rows) {
                if ($table === 'waste_imports') {
                    foreach ($rows as &$row) {
                        // A prototype user ID does not identify a production user. Approval time and history are retained.
                        $row['adr_approved_by'] = null;
                    }
                    unset($row);
                }
                foreach (array_chunk($rows, 40) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
                if (DB::table($table)->count() !== $counts[$table]) {
                    throw new RuntimeException('Aktarım kayıt sayısı doğrulanamadı: '.$table);
                }
            }
        });
        $this->info('Atık Takip verisi aktarıldı. Modül kapalı.');
        return self::SUCCESS;
    }
}
