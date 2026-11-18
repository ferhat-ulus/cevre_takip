<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class WasteBackupController extends Controller
{
    public function download()
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        abort_unless(in_array($driver, ['sqlite', 'mysql'], true), 503, 'Bu veritabanı için yedekleme tanımlı değil.');
        $directory = storage_path('app/private');
        $snapshot = tempnam($directory, 'waste-backup-');
        if ($snapshot === false) throw new RuntimeException('Yedek dosyası oluşturulamadı.');

        try {
            if ($driver === 'sqlite') {
                $pdo = $connection->getPdo();
                $pdo->exec('VACUUM INTO '.$pdo->quote($snapshot));
                $copy = new PDO('sqlite:'.$snapshot);
                if ($copy->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
                    throw new RuntimeException('Yedek bütünlük kontrolünü geçemedi.');
                }
                $copy = null;
            } else {
                $config = $connection->getConfig();
                $socket = $config['unix_socket'] ?? '';
                $location = $socket
                    ? ['--socket='.$socket]
                    : ['--protocol=TCP', '--host='.($config['host'] ?? '127.0.0.1'), '--port='.($config['port'] ?? '3306')];
                $process = new Process([
                    config('waste_backup.mysql_dump_binary'), '--no-defaults', ...$location,
                    '--user='.$config['username'], '--single-transaction', '--quick', '--no-tablespaces',
                    '--default-character-set=utf8mb4', '--result-file='.$snapshot, '--', $connection->getDatabaseName(),
                ], null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
                $process->setTimeout(120);
                if ($process->run() !== 0) throw new RuntimeException('MySQL yedeği oluşturulamadı.');
            }
            clearstatcache(true, $snapshot);
            if (!filesize($snapshot)) throw new RuntimeException('Yedek dosyası boş.');
            return response()->download($snapshot, 'Atik-Takip-'.now('Europe/Istanbul')->format('Y-m-d_His').($driver === 'sqlite' ? '.sqlite' : '.sql'), [
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend();
        } catch (Throwable $error) {
            @unlink($snapshot);
            throw $error;
        }
    }
}
