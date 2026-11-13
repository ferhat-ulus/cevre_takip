<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Firm;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ComplianceEngine;
use App\Services\FirmExcelTemplateMapper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

class ExcelController extends Controller
{
    public function index()
    {
        return Inertia::render('ExcelAktar', [
            'hasTemplate' => file_exists(storage_path('app/templates/master_template.xlsx')),
            'isAdmin' => request()->user()?->role === 'admin',
        ]);
    }

    public function uploadTemplate(Request $request)
    {
        $request->validate(['template_file' => 'required|file|mimes:xlsx,xls']);
        try {
            $path = storage_path('app/templates');
            if (!file_exists($path)) mkdir($path, 0775, true);
            $spreadsheet = IOFactory::load($request->file('template_file')->getPathname());
            FirmExcelTemplateMapper::removeLegacyStatusOptions($spreadsheet);
            (new Xlsx($spreadsheet))->save($path . DIRECTORY_SEPARATOR . 'master_template.xlsx');
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
            if (!is_array($selectedData)) {
                return response()->json(['error' => 'Aktarılacak veri okunamadı.'], 422);
            }
            $result = $this->commitExcel($request->file('file'), $request->user(), $selectedData);
            
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'excel_import',
                'description' => "Excel'den toplu veri aktarımı yapıldı. İşlenen tesis sayısı: " . $result['imported'],
                'ip_address' => $request->ip()
            ]);

            return response()->json($result);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Aktarım Hatası: ' . $e->getMessage()], 400);
        }
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
        
        $colMap = FirmExcelTemplateMapper::findColumnMappings($worksheet, $headerRow);
        $existingFirms = Firm::all()->keyBy(fn($f) => mb_strtoupper(trim($f->data['name'] ?? ''), 'UTF-8'));
        
        $rows = []; $unread = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $name = trim($worksheet->getCell(($colMap['firma'] ?? 'A') . "{$row}")->getCalculatedValue() ?? '');
            if (empty($name)) continue;
            
            $existingFirm = $existingFirms->get(mb_strtoupper($name, 'UTF-8'));
            $isNew = $existingFirm === null;
            $existingData = $isNew ? [] : $existingFirm->data;

            if (!$isNew && $user->role !== 'admin'
                && !$existingFirm->isAssignedTo($user)) {
                $unread[] = [
                    'row' => $row,
                    'col' => $colMap['firma'] ?? 'A',
                    'value' => $name,
                    'topic' => 'Tesis yetkisi',
                    'message' => 'Bu tesis başka bir danışmana ait olduğu için aktarıma dahil edilmedi.',
                ];
                continue;
            }
            $items = []; $changes = [];
            
            foreach ($colMap['items'] as $k => $col) {
                if (!$col) continue;
                $val = $worksheet->getCell("{$col}{$row}")->getCalculatedValue();
                $parsed = $this->parseExcelCell($val, $k, $unread, $row, $col);
                if ($parsed['s'] !== null || $isNew || !empty($parsed['_clear'])) {
                    $items[$k] = $parsed;
                    if (!$isNew) {
                        $oldItem = $existingData['items'][$k] ?? [];
                        if (($oldItem['s'] ?? null) !== $parsed['s']
                            || ($oldItem['d'] ?? null) !== ($parsed['d'] ?? null)
                            || ($oldItem['v'] ?? null) !== ($parsed['v'] ?? null)) {
                            $changes[] = ['key' => $k, 'name' => $this->getTopicName($k), 'old' => $this->formatItemForDiff($oldItem), 'new' => $this->formatItemForDiff($parsed)];
                        }
                    }
                }
            }
            
            $cedColumn = $colMap['cedKarari'] ?? 'K';
            $cedRaw = $worksheet->getCell("{$cedColumn}{$row}")->getCalculatedValue() ?? '';
            if (ComplianceEngine::isLegacyMissingValue(is_string($cedRaw) ? $cedRaw : null)) {
                $ced = null;
                $unread[] = [
                    'row' => $row,
                    'col' => $cedColumn,
                    'value' => $cedRaw,
                    'topic' => 'ÇED kararı',
                    'message' => 'Bu değer veri yok kabul edilir; mevcut ÇED kararı temizlenecek.',
                ];
            } else {
                $ced = mb_strtoupper(str_replace('ÇED KAPSAM DIŞI', 'ÇED KAPSAMDIŞI', $cedRaw), 'UTF-8') ?: null;
            }
            $cd = $worksheet->getCell(($colMap['cedTarihi'] ?? 'L') . "{$row}")->getCalculatedValue();
            $consultantName = trim($worksheet->getCell(($colMap['danisman'] ?? 'B') . "{$row}")->getCalculatedValue() ?? '');
            $consultantId = $user->role === 'admin'
                ? $this->resolveConsultantId($consultantName, $unread, $row, $colMap['danisman'] ?? 'B')
                : $user->id;
            
            $rows[] = [
                'row' => $row, 'name' => $name, 'is_new' => $isNew, 'consultant' => $consultantName, 'consultant_user_id' => $consultantId,
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
        $existingFirms = Firm::all()->keyBy(fn($f) => mb_strtoupper(trim($f->data['name'] ?? ''), 'UTF-8'));
        $imported = 0;
        
        foreach ($selectedRows as $rowData) {
            if (!isset($rowData['selected']) || !$rowData['selected']) continue;
            $name = trim((string) ($rowData['name'] ?? ''));
            if ($name === '') continue;
            $firm = $existingFirms->get(mb_strtoupper($name, 'UTF-8'));
            $isNew = $firm === null;
            if (!$isNew && $user->role !== 'admin' && !$firm->isAssignedTo($user)) {
                throw new AuthorizationException('Yalnızca size atanmış tesislerin Excel verisini güncelleyebilirsiniz.');
            }
            $selectedItems = [];
            if (isset($rowData['items']) && is_array($rowData['items'])) {
                foreach ($rowData['items'] as $k => $it) {
                    if (isset($rowData['item_selections'][$k]) && $rowData['item_selections'][$k] === false) continue;
                    unset($it['_clear']);
                    $selectedItems[$k] = $it;
                }
            }
            if ($isNew) {
                $consultantId = $user->role === 'admin' ? ($rowData['consultant_user_id'] ?? null) : $user->id;
                Firm::create(['id' => substr(uniqid(), -8), 'consultant_user_id' => $consultantId ?: null, 'data' => ['name' => $name, 'sector' => $rowData['sector'] ?? '', 'status' => 'aktif', 'note' => $rowData['note'] ?? '', 'contact' => '', 'profile' => $rowData['profile'] ?? [], 'items' => $selectedItems, 'updatedAt' => now()->toDateTimeString(), 'updatedBy' => $user->name]]);
            } else {
                $data = $firm->data;
                foreach ($selectedItems as $k => $it) {
                    $data['items'][$k] = !empty($rowData['items'][$k]['_clear'])
                        ? $it
                        : array_merge($data['items'][$k] ?? [], $it);
                    $data['items'][$k]['u'] = now()->toDateTimeString(); $data['items'][$k]['ub'] = $user->name;
                }
                $data['profile'] = array_merge($data['profile'] ?? [], $rowData['profile']);
                $data['note'] = $rowData['note'] ?: ($data['note'] ?? '');
                if ($user->role === 'admin' && array_key_exists('consultant_user_id', $rowData)) $firm->consultant_user_id = $rowData['consultant_user_id'] ?: null;
                if ($rowData['sector']) $data['sector'] = $rowData['sector'];
                $data['updatedAt'] = now()->toDateTimeString(); $data['updatedBy'] = $user->name;
                unset($data['consultant']);
                $firm->data = $data; $firm->save();
            }
            $imported++;
        }
        return ['imported' => $imported];
    }

    private function resolveConsultantId(string $name, array &$unread, int $row, string $column): ?int
    {
        $name = trim($name);
        if ($name === '') return null;

        $matches = User::activeConsultants()->where('name', $name)->get(['id']);
        if ($matches->count() === 1) return $matches->first()->id;

        $unread[] = [
            'row' => $row, 'col' => $column, 'value' => $name, 'topic' => 'Danışman',
            'message' => $matches->isEmpty()
                ? 'Bu adla aktif danışman bulunamadı; tesis danışmansız aktarılacak.'
                : 'Bu adla birden fazla danışman var; tesis danışmansız aktarılacak. Kullanıcı yönetiminden atama yapın.',
        ];
        return null;
    }

    private function getTopicName($key) { 
        $ob = ComplianceEngine::obl($key);
        return $ob ? $ob['n'] : $key;
    }
    
        private function formatItemForDiff($item) {
        if (empty($item['s'])) return 'Girilmedi';
        if ($item['s'] === 'muaf') return $item['v'] ?? 'Kapsam Dışı';
        if ($item['s'] === 'secim') return $item['v'] ?? 'Seçim';
        if ($item['s'] === 'surec') return 'Veri Yok';
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

    private function parseExcelCell($v, $k, &$unread, $row, $col) {
        if ($v === null || (is_string($v) && trim($v) === '') || $v === '-') return ['s' => null];
        if (ComplianceEngine::isLegacyMissingValue(is_string($v) ? $v : null)) {
            $unread[] = [
                'row' => $row,
                'col' => $col,
                'value' => $v,
                'topic' => $this->getTopicName($k),
                'message' => 'Bu değer veri yok kabul edilir; mevcut kayıt temizlenecek.',
            ];
            return ['s' => null, '_clear' => true];
        }
        if (is_string($v)) {
            $u = $this->normalizeStr($v);
            if (strpos($u, 'MUAF') !== false || strpos($u, 'KAPSAM') !== false || preg_match('/\bKD\b/', $u) || $u === 'REVIR YOK') {
                return ['s' => 'muaf', 'v' => trim($v)];
            }
        }
        $d = $this->parseDate($v);
        if ($d) return ['s' => 'tarih', 'd' => $d];
        if (is_string($v)) return ['s' => 'secim', 'v' => trim($v)];
        $unread[] = ['row' => $row, 'col' => $col, 'value' => $v, 'topic' => $this->getTopicName($k)];
        return ['s' => null];
    }

    private function parseDate($v) { 
        if ($v === null || $v === '') return null;
        if (is_numeric($v) && $v > 1 && $v < 100000) { try { return Date::excelToDateTimeObject($v)->format('Y-m-d'); } catch (Throwable $e) { return null; } }
        if (is_string($v)) {
            $v = trim($v);
            if (preg_match('/\d+/', $v) && !preg_match('/(MUAF|KAPSAM|KD)/i', $v)) { try { return Carbon::parse($v)->format('Y-m-d'); } catch (Throwable $e) { return null; } }
        }
        if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
        return null;
    }

}
