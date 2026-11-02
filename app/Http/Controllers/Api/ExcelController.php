<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Firm;
use App\Models\Visit;
use App\Models\ActivityLog;
use App\Services\ComplianceEngine;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExcelController extends Controller
{
    public function index()
    {
        return Inertia::render('ExcelAktar', [
            'hasTemplate' => file_exists(storage_path('app/templates/master_template.xlsx'))
        ]);
    }

    public function uploadTemplate(Request $request)
    {
        $request->validate(['template_file' => 'required|file|mimes:xlsx,xls']);
        try {
            $path = storage_path('app/templates');
            if (!file_exists($path)) mkdir($path, 0775, true);
            $request->file('template_file')->move($path, 'master_template.xlsx');
            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Şablon yüklenemedi: ' . $e->getMessage()], 400);
        }
    }

    public function preview(Request $request)
    {
        try {
            $request->validate(['file' => 'required|file']);
            $result = $this->parseExcel($request->file('file'), $request->user(), false);
            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Doğrulama Hatası: ' . collect($e->errors())->flatten()->first()], 422);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Okuma Hatası: ' . $e->getMessage()], 400);
        }
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file' => 'required|file', 'selected_data' => 'required|json']);
            $selectedData = json_decode($request->selected_data, true);
            $result = $this->commitExcel($request->file('file'), $request->user(), $selectedData);
            
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'excel_import',
                'description' => "Excel'den toplu veri aktarımı yapıldı. İşlenen tesis sayısı: " . $result['imported'],
                'ip_address' => $request->ip()
            ]);

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Aktarım Hatası: ' . $e->getMessage()], 400);
        }
    }

    public function destroyAll(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Bu işlem için yönetici yetkisi gerekiyor.'], 403);
        }

        try {
            $exportController = new ExportController();
            $backupFileName = $exportController->generateBackupFile($request->user());
            $backupPath = storage_path('app/backups/' . $backupFileName);
            if (!file_exists($backupPath)) throw new \Exception('Yedek dosyası oluşturulamadı. İşlem iptal edildi.');

            Visit::truncate();
            Firm::truncate();

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'database_wipe',
                'description' => "DİKKAT: Tüm tesis ve ziyaret verileri silindi! (Sistem yedeği alındı)",
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Tüm veriler silindi. Sistem yedeği indiriliyor.',
                'download_url' => url('/api/download-backup/' . $backupFileName)
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'İşlem başarısız: ' . $e->getMessage()], 500);
        }
    }

    public function downloadBackup($filename)
    {
        $path = storage_path('app/backups/' . $filename);
        if (!file_exists($path)) abort(404, 'Yedek dosyası bulunamadı.');
        return response()->download($path);
    }

    private function parseExcel($file, $user, $commit)
    {
        if (!$file || !$file->isValid()) throw new \Exception('Dosya yüklenemedi.');
        set_time_limit(300);
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getSheet(0);
        $highestRow = $worksheet->getHighestDataRow();
        $headerRow = null;
        for ($r = 1; $r <= 10; $r++) {
            $val = $this->normalizeStr($worksheet->getCell("A{$r}")->getValue());
            if (mb_strpos($val, 'FIRMA') !== false) { $headerRow = $r; break; }
        }
        if (!$headerRow) throw new \Exception('Excel dosyasında "FİRMA" başlığı bulunamadı.');
        
        $colMap = $this->findColumnMappings($worksheet, $headerRow);
        $existingFirms = Firm::all()->keyBy(fn($f) => mb_strtoupper(trim($f->data['name'] ?? ''), 'UTF-8'));
        
        $rows = []; $unread = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $name = trim($worksheet->getCell(($colMap['firma'] ?? 'A') . "{$row}")->getCalculatedValue() ?? '');
            if (empty($name)) continue;
            
            $isNew = !$existingFirms->has(mb_strtoupper($name, 'UTF-8'));
            $existingData = $isNew ? [] : $existingFirms->get(mb_strtoupper($name, 'UTF-8'))->data;
            $items = []; $changes = [];
            
            foreach ($colMap['items'] as $k => $col) {
                if (!$col) continue;
                $val = $worksheet->getCell("{$col}{$row}")->getCalculatedValue();
                $parsed = $this->parseExcelCell($val, $k, $unread, $row, $col);
                if ($parsed['s'] !== null || $isNew) {
                    $items[$k] = $parsed;
                    if (!$isNew) {
                        $oldItem = $existingData['items'][$k] ?? [];
                        if (($oldItem['s'] ?? null) !== $parsed['s'] || ($oldItem['d'] ?? null) !== ($parsed['d'] ?? null)) {
                            $changes[] = ['key' => $k, 'name' => $this->getTopicName($k), 'old' => $this->formatItemForDiff($oldItem), 'new' => $this->formatItemForDiff($parsed)];
                        }
                    }
                }
            }
            
            $cedRaw = $worksheet->getCell(($colMap['cedKarari'] ?? 'K') . "{$row}")->getCalculatedValue() ?? '';
            $ced = mb_strtoupper(str_replace('ÇED KAPSAM DIŞI', 'ÇED KAPSAMDIŞI', $cedRaw), 'UTF-8') ?: null;
            $cd = $worksheet->getCell(($colMap['cedTarihi'] ?? 'L') . "{$row}")->getCalculatedValue();
            $consultant = mb_strtoupper(trim($worksheet->getCell(($colMap['danisman'] ?? 'B') . "{$row}")->getCalculatedValue() ?? ''), 'UTF-8');
            if ($user->role !== 'admin') $consultant = $user->name;
            
            $rows[] = [
                'row' => $row, 'name' => $name, 'is_new' => $isNew, 'consultant' => $consultant,
                'sector' => mb_strtoupper(trim($worksheet->getCell(($colMap['sektor'] ?? 'C') . "{$row}")->getCalculatedValue() ?? ''), 'UTF-8'),
                'note' => trim($worksheet->getCell(($colMap['note'] ?? 'D') . "{$row}")->getCalculatedValue() ?? ''),
                'items' => $items, 'changes' => $changes, 
                'profile' => [
                    'cedKarari' => $ced, 'cedTarihi' => $this->parseDate($cd),
                    'izinKonusu' => mb_strtoupper(trim($worksheet->getCell(($colMap['izinKonusu'] ?? 'M') . "{$row}")->getCalculatedValue() ?? ''), 'UTF-8') ?: null,
                    'atiksuKurum' => mb_strtoupper(trim($worksheet->getCell(($colMap['atiksuKurum'] ?? 'AF') . "{$row}")->getCalculatedValue() ?? ''), 'UTF-8') ?: null,
                    'atiksuTipi' => mb_strtoupper(trim($worksheet->getCell(($colMap['atiksuTipi'] ?? 'AG') . "{$row}")->getCalculatedValue() ?? ''), 'UTF-8') ?: null
                ]
            ];
        }
        return ['rows' => $rows, 'unread_cells' => $unread, 'total' => count($rows)];
    }

    private function commitExcel($file, $user, $selectedRows)
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $existingFirms = Firm::all()->keyBy(fn($f) => mb_strtoupper(trim($f->data['name'] ?? ''), 'UTF-8'));
        $imported = 0;
        
        foreach ($selectedRows as $rowData) {
            if (!isset($rowData['selected']) || !$rowData['selected']) continue;
            $name = $rowData['name']; $isNew = $rowData['is_new'];
            $selectedItems = [];
            if (isset($rowData['items']) && is_array($rowData['items'])) {
                foreach ($rowData['items'] as $k => $it) {
                    if (isset($rowData['item_selections'][$k]) && $rowData['item_selections'][$k] === false) continue;
                    $selectedItems[$k] = $it;
                }
            }
            if ($isNew) {
                Firm::create(['id' => substr(uniqid(), -8), 'data' => ['name' => $name, 'consultant' => $rowData['consultant'], 'sector' => $rowData['sector'], 'status' => 'aktif', 'note' => $rowData['note'], 'contact' => '', 'profile' => $rowData['profile'], 'items' => $selectedItems, 'updatedAt' => now()->toDateTimeString(), 'updatedBy' => $user->name]]);
            } else {
                $firm = $existingFirms->get(mb_strtoupper($name, 'UTF-8'));
                if (!$firm) continue;
                $data = $firm->data;
                foreach ($selectedItems as $k => $it) {
                    $data['items'][$k] = array_merge($data['items'][$k] ?? [], $it);
                    $data['items'][$k]['u'] = now()->toDateTimeString(); $data['items'][$k]['ub'] = $user->name;
                }
                $data['profile'] = array_merge($data['profile'] ?? [], $rowData['profile']);
                $data['note'] = $rowData['note'] ?: ($data['note'] ?? '');
                if ($user->role === 'admin' && $rowData['consultant']) $data['consultant'] = $rowData['consultant'];
                if ($rowData['sector']) $data['sector'] = $rowData['sector'];
                $data['updatedAt'] = now()->toDateTimeString(); $data['updatedBy'] = $user->name;
                $firm->data = $data; $firm->save();
            }
            $imported++;
        }
        return ['imported' => $imported];
    }

    private function getTopicName($key) { 
        $ob = ComplianceEngine::obl($key);
        return $ob ? $ob['n'] : $key;
    }
    
    private function formatItemForDiff($item) {
        if (empty($item['s'])) return 'Girilmedi';
        if ($item['s'] === 'muaf') return 'Kapsam Dışı';
        if ($item['s'] === 'surec') return 'Süreçte';
        if ($item['s'] === 'tarih') return !empty($item['d']) ? Carbon::parse($item['d'])->format('d.m.Y') : 'Tarih Yok';
        return $item['s'];
    }
    
    private function normalizeStr($str) { 
        $str = trim($str ?? '');
        $turkish = ['İ', 'ı', 'Ş', 'ş', 'Ğ', 'ğ', 'Ü', 'ü', 'Ö', 'ö', 'Ç', 'ç'];
        $english = ['I', 'i', 'S', 's', 'G', 'g', 'U', 'u', 'O', 'o', 'C', 'c'];
        $str = str_replace($turkish, $english, $str);
        $str = mb_strtoupper($str, 'UTF-8');
        $str = preg_replace('/[^A-Z0-9\s\/]/', '', $str);
        return preg_replace('/\s+/', ' ', trim($str));
    }

    private function findColumnMappings($worksheet, $headerRow) 
    { 
        $mapping = [
            'firma' => ['FIRMA'], 'danisman' => ['DANISMAN'], 'sektor' => ['SEKTOR'], 
            'note' => ['FIRMA DURUM', 'OZEL NOT', 'PROBLEM'], 'cedKarari' => ['CED KARARI'], 
            'cedTarihi' => ['CED GORUS TARIHI'], 'izinKonusu' => ['CEVRE IZIN KONUSU'], 
            'atiksuKurum' => ['ATIKSU GORUS KURUMU'], 'atiksuTipi' => ['ATIKSU TIPI']
        ];
        
        // YENİ: Mevzuatları dinamik olarak çek ve sütun eşleştirmelerini oluştur
        $obligations = ComplianceEngine::getAll();
        $itemsMapping = [];
        foreach ($obligations as $ob) {
            $k = $ob['k'];
            $n = mb_strtoupper($ob['n'], 'UTF-8');
            $s = mb_strtoupper($ob['s'] ?? $k, 'UTF-8');
            
            // Excel başlığında aranacak isimler
            $searchTerms = [$n, $s, mb_strtoupper($k, 'UTF-8')];
            $itemsMapping[$k] = array_unique($searchTerms);
        }
        $mapping['items'] = $itemsMapping;
        
        $headers = [];
        $highestCol = $worksheet->getHighestDataColumn();
        $colIterator = $worksheet->getColumnIterator('A', $highestCol);
        foreach ($colIterator as $column) {
            $colLetter = $column->getColumnIndex();
            $val = $this->normalizeStr($worksheet->getCell($colLetter . $headerRow)->getValue());
            if (!empty($val)) $headers[$colLetter] = $val;
        }
        
        $result = ['items' => []];
        $searchKeys = [
            'firma' => $mapping['firma'], 'danisman' => $mapping['danisman'], 'sektor' => $mapping['sektor'], 
            'note' => $mapping['note'], 'cedKarari' => $mapping['cedKarari'], 'cedTarihi' => $mapping['cedTarihi'], 
            'izinKonusu' => $mapping['izinKonusu'], 'atiksuKurum' => $mapping['atiksuKurum'], 'atiksuTipi' => $mapping['atiksuTipi']
        ];
        foreach ($mapping['items'] as $k => $searches) $searchKeys["items.{$k}"] = $searches;
        
        foreach ($searchKeys as $key => $searchList) {
            $foundCol = null;
            foreach ($searchList as $search) {
                $s = $this->normalizeStr($search);
                foreach ($headers as $col => $headerVal) {
                    if (mb_strpos($headerVal, $s) !== false) {
                        $excludeWords = ['BITIS', 'KALAN', 'SONRAKI', 'GUNCELLEME', 'SON BASVURU'];
                        $hasExclude = false;
                        foreach ($excludeWords as $ex) if (mb_strpos($headerVal, $ex) !== false) { $hasExclude = true; break; }
                        if ($hasExclude && !in_array($s, ['FIRMA DURUM', 'OZEL NOT', 'PROBLEM'])) continue; 
                        $foundCol = $col; break 2;
                    }
                }
            }
            if (strpos($key, 'items.') === 0) $result['items'][str_replace('items.', '', $key)] = $foundCol;
            else $result[$key] = $foundCol;
        }
        return $result;
    }

    private function parseExcelCell($v, $k, &$unread, $row, $col) { 
        if ($v === null || (is_string($v) && trim($v) === '') || $v === '-') return ['s' => null];
        if (is_string($v)) {
            $u = $this->normalizeStr($v);
            if (strpos($u, 'SUREC') !== false) return ['s' => 'surec'];
            if (strpos($u, 'MUAF') !== false || strpos($u, 'KAPSAM') !== false || preg_match('/\bKD\b/', $u)) return ['s' => 'muaf'];
        }
        $d = $this->parseDate($v);
        if ($d) return ['s' => 'tarih', 'd' => $d];
        $unread[] = ['row' => $row, 'col' => $col, 'value' => $v, 'topic' => $this->getTopicName($k)];
        return ['s' => null];
    }

    private function parseDate($v) { 
        if ($v === null || $v === '') return null;
        if (is_numeric($v) && $v > 1 && $v < 100000) { try { return Date::excelToDateTimeObject($v)->format('Y-m-d'); } catch (Throwable $e) { return null; } }
        if (is_string($v)) {
            $v = trim($v);
            if (preg_match('/\d+/', $v) && !preg_match('/(MUAF|SÜREÇTE|KAPSAM|KD)/i', $v)) { try { return Carbon::parse($v)->format('Y-m-d'); } catch (Throwable $e) { return null; } }
        }
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
        return null;
    }
}