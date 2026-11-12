<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class BackupController extends Controller
{
    private function backupPath($adapter, string $filename): string
    {
        abort_unless(basename($filename) === $filename && str_ends_with($filename, '.zip'), 404);

        $directory = $adapter->exists('private/Laravel') ? 'private/Laravel' : 'Laravel';

        return $directory.'/'.$filename;
    }

    public function index(Request $request)
    {
        // Laravel 11 ve 10 arasındaki klasör farkını otomatik çözer
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $adapter = Storage::disk($disk);
        
        // Laravel 11 için 'private/Laravel', Laravel 10 için 'Laravel' klasörü
        $path = $adapter->exists('private/Laravel') ? 'private/Laravel' : 'Laravel';
        
        $files = $adapter->files($path);
        
        $backups = array_map(function ($file) use ($adapter) {
            return [
                'name' => basename($file),
                'size' => round($adapter->size($file) / 1024 / 1024, 2) . ' MB',
                'date' => date('d.m.Y H:i', $adapter->lastModified($file)),
            ];
        }, $files);

        // En yeniyi en üste al
        usort($backups, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

        return Inertia::render('Yedekler', [
            'backups' => $backups
        ]);
    }

    public function run(Request $request)
    {
        try {
            Artisan::call('backup:run');
            return response()->json(['ok' => true, 'message' => 'Yedek başarıyla alındı.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Yedek alınamadı: ' . $e->getMessage()], 500);
        }
    }

    public function download(Request $request, $filename)
    {
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $adapter = Storage::disk($disk);
        $path = $this->backupPath($adapter, $filename);

        if (!$adapter->exists($path)) {
            abort(404, 'Yedek dosyası bulunamadı.');
        }

        return $adapter->download($path);
    }

    public function destroy(Request $request, $filename)
    {
        $disk = config('backup.backup.destination.disks')[0] ?? 'local';
        $adapter = Storage::disk($disk);
        $path = $this->backupPath($adapter, $filename);

        if ($adapter->exists($path)) {
            $adapter->delete($path);
            return response()->json(['ok' => true]);
        }

        return response()->json(['error' => 'Dosya bulunamadı.'], 404);
    }
}
