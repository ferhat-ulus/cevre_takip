<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Firm;
use App\Models\Config;
use App\Services\ComplianceEngine;
use App\Services\FirmExcelTemplateMapper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;
use Throwable;

class ExportController extends Controller
{
    public function exportExcel(Request $request)
    {
        try {
            $user = $request->user();
            $consultantFilter = $request->input('consultant', 'all');
            $statusFilter = $request->input('status', 'all');
            $templatePath = storage_path('app/templates/master_template.xlsx');
            $useTemplate = filter_var($request->input('use_template', file_exists($templatePath)), FILTER_VALIDATE_BOOLEAN);

            $firmsRaw = Firm::with('consultant:id,name')->visibleTo($user)->get();
            $firms = [];
            $today = Carbon::today();
            $cfgRow = Config::find('main');
            $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

            foreach ($firmsRaw as $firm) {
                if ($user->role === 'admin' && $consultantFilter !== 'all') {
                    if ((int) $firm->consultant_user_id !== (int) $consultantFilter) continue;
                }

                $data = $firm->data;
                $data['id'] = $firm->id;
                $data['consultant'] = $firm->consultantName();

                if ($statusFilter !== 'all') {
                    $ev = ComplianceEngine::evaluateFirm($data, $today, $cfg);
                    $hasStatus = false;
                    foreach ($ev as $e) {
                        if ($e['state'] === $statusFilter) {
                            $hasStatus = true;
                            break;
                        }
                    }
                    if (!$hasStatus) continue;
                }

                $firms[] = $data;
            }

            if (count($firms) === 0) {
                return response()->json(['error' => 'Seçilen kriterlere uygun tesis bulunamadı.'], 400);
            }

            if ($useTemplate && file_exists($templatePath)) {
                $spreadsheet = IOFactory::load($templatePath);
                $worksheet = $spreadsheet->getSheet(0);
                
                $headerRow = null;
                for ($r = 1; $r <= 15; $r++) {
                    $val = $this->normalizeStr($worksheet->getCell("A{$r}")->getValue());
                    if (mb_strpos($val, 'FIRMA') !== false) { $headerRow = $r; break; }
                }
                
                if (!$headerRow) {
                    return response()->json(['error' => 'Şablon dosyasında "FİRMA" başlığı bulunamadı.'], 400);
                }

                // 1. Şablondaki mevcut sütunları bul
                $colMap = FirmExcelTemplateMapper::findColumnMappings($worksheet, $headerRow);
                FirmExcelTemplateMapper::repairExternalDropdowns($spreadsheet, $worksheet);
                
                // 2. Sistemdeki tüm mevzuatları çek
                $allObligations = ComplianceEngine::getAll();
                
                // 3. Şablonda EKSİK olan mevzuatları tespit et ve yeni sütunlar ekle
                $lastColIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
                
                foreach ($allObligations as $ob) {
                    $k = $ob['k'];
                    if (($ob['kind'] ?? null) === 'linked_izin') continue;
                    // Eğer şablonda bu mevzuat için bir sütun bulunamadıysa
                    if (!isset($colMap['items'][$k]) || $colMap['items'][$k] === null) {
                        $lastColIndex++;
                        $newColLetter = Coordinate::stringFromColumnIndex($lastColIndex);
                        
                        // Yeni sütun başlığını yaz
                        $headerCell = $worksheet->getCell("{$newColLetter}{$headerRow}");
                        $headerCell->setValue(mb_strtoupper($ob['n'], 'UTF-8'));
                        
                        // Başlık stilini bir önceki sütundan kopyala (uyumlu görünmesi için)
                        $prevColLetter = Coordinate::stringFromColumnIndex($lastColIndex - 1);
                        $worksheet->duplicateStyle($worksheet->getStyle("{$prevColLetter}{$headerRow}"), "{$newColLetter}{$headerRow}");
                        $worksheet->getColumnDimension($newColLetter)->setWidth(20); // Varsayılan genişlik

                        // Haritaya yeni sütunu ekle ki veri yazılabilsin
                        $colMap['items'][$k] = $newColLetter;
                    }
                }

                // 4. Tesis verilerini Excel'e yaz
                $existingFirmMap = [];
                foreach ($firms as $f) {
                    $existingFirmMap[$this->normalizeStr($f['name'])] = $f;
                }

                $highestRow = $worksheet->getHighestDataRow();
                $templateFirmsMap = [];
                
                for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                    $name = $this->normalizeStr($worksheet->getCell(($colMap['firma'] ?? 'A') . "{$row}")->getCalculatedValue());
                    if (!empty($name)) $templateFirmsMap[$name] = $row;
                }

                $nextEmptyRow = $headerRow + 1;
                while ($nextEmptyRow <= $highestRow && trim($worksheet->getCell("A{$nextEmptyRow}")->getValue() ?? '') !== '') {
                    $nextEmptyRow++;
                }

                foreach ($existingFirmMap as $normName => $firm) {
                    if (isset($templateFirmsMap[$normName])) {
                        // Şablonda zaten olan tesisi güncelle
                        $this->writeFirmDataToRow($worksheet, $templateFirmsMap[$normName], $firm, $colMap);
                    } else {
                        // Şablonda olmayan yeni tesisi en alta ekle
                        if ($nextEmptyRow > $highestRow || empty($worksheet->getCell("F{$nextEmptyRow}")->getValue())) {
                            $prevRow = $nextEmptyRow - 1;
                            if ($prevRow > $headerRow) {
                                $worksheet->getRowDimension($nextEmptyRow)->setRowHeight($worksheet->getRowDimension($prevRow)->getRowHeight());
                                
                                // Tüm sütunlar için stil, formül ve AÇILIR LİSTELERİ kopyala
                                for ($i = 1; $i <= $lastColIndex; $i++) {
                                    $colLetter = Coordinate::stringFromColumnIndex($i);
                                    $prevCoordinate = "{$colLetter}{$prevRow}";
                                    $newCoordinate = "{$colLetter}{$nextEmptyRow}";

                                    // 1. Stili Kopyala
                                    $worksheet->duplicateStyle($worksheet->getStyle($prevCoordinate), $newCoordinate);

                                    // 2. Açılır Listeleri (Data Validation) Kopyala
                                    $validation = clone $worksheet->getCell($prevCoordinate)->getDataValidation();
                                    if ($validation->getType() !== \PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_NONE) {
                                        $worksheet->getCell($newCoordinate)->setDataValidation($validation);
                                    }

                                    // 3. Formülleri Kopyala
                                    $formula = $worksheet->getCell($prevCoordinate)->getValue();
                                    if (is_string($formula) && str_starts_with($formula, '=')) {
                                        // Formül içindeki satır numaralarını yeni satıra göre güncelle
                                        $newFormula = preg_replace('/([A-Z]+)' . $prevRow . '/', '${1}' . $nextEmptyRow, $formula);
                                        $worksheet->getCell($newCoordinate)->setValue($newFormula);
                                    }
                                }
                            }
                        }
                        
                        $worksheet->getCell("{$colMap['firma']}{$nextEmptyRow}")->setValue($firm['name']);
                        $this->writeFirmDataToRow($worksheet, $nextEmptyRow, $firm, $colMap);
                        $nextEmptyRow++;
                    }
                }
            } else {
                // Şablon yoksa veya kullanılmak istenmiyorsa varsayılan format
                $spreadsheet = $this->generateDefaultSpreadsheet($firms, $user);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'cevre-takip-' . now()->format('Ymd_His') . '.xlsx';
            $tempPath = storage_path('app/public/' . $filename);
            
            if (!file_exists(storage_path('app/public'))) mkdir(storage_path('app/public'), 0775, true);
            $writer->save($tempPath);

            ob_end_clean();
            return response()->download($tempPath)->deleteFileAfterSend(true);

        } catch (Throwable $e) {
            return response()->json(['error' => 'Sistem Hatası: ' . $e->getMessage()], 500);
        }
    }

        private function writeFirmDataToRow($worksheet, $row, $firm, $colMap)
    {
        if (isset($colMap['danisman'])) $worksheet->getCell("{$colMap['danisman']}{$row}")->setValue($firm['consultant'] ?? '');
        if (isset($colMap['sektor'])) $worksheet->getCell("{$colMap['sektor']}{$row}")->setValue($firm['sector'] ?? '');
        if (isset($colMap['note'])) $worksheet->getCell("{$colMap['note']}{$row}")->setValue($firm['note'] ?? '');

        foreach ($colMap['items'] as $k => $col) {
            if (!$col) continue;
            $item = $firm['items'][$k] ?? null;
            $cell = $worksheet->getCell("{$col}{$row}");

            if (!$item || empty($item['s']) || $item['s'] === 'surec') {
                $cell->setValue(null);
                continue;
            }

            if ($item['s'] === 'tarih' && !empty($item['d'])) {
                $cell->setValue(Date::PHPToExcel(Carbon::parse($item['d'])));
                $cell->getStyle()->getNumberFormat()->setFormatCode('DD.MM.YYYY');
            } elseif ($item['s'] === 'muaf') {
                $defaultValue = match ($k) {
                    'kapasite', 'izin', 'syd' => 'KAPSAMDIŞI',
                    'tayp' => 'REVİR YOK',
                    default => 'MUAF',
                };
                $cell->setValue($item['v'] ?? $defaultValue);
            } elseif ($item['s'] === 'secim' && !empty($item['v'])) {
                $cell->setValue($item['v']);
            }
        }

        $cedKarari = $firm['profile']['cedKarari'] ?? null;
        $cedTarihi = $firm['profile']['cedTarihi'] ?? null;
        $clearLegacyCed = ComplianceEngine::isLegacyMissingValue(is_string($cedKarari) ? $cedKarari : null);
        if ($clearLegacyCed) {
            $cedKarari = null;
            $cedTarihi = null;
        }
        if (isset($colMap['cedKarari'])) {
            $worksheet->getCell("{$colMap['cedKarari']}{$row}")->setValue($cedKarari);
        }
        if (isset($colMap['cedTarihi'])) {
            $dateCell = $worksheet->getCell("{$colMap['cedTarihi']}{$row}");
            if ($clearLegacyCed) {
                $dateCell->setValue(null);
            } elseif (!empty($cedTarihi)) {
                $dateCell->setValue(Date::PHPToExcel(Carbon::parse($cedTarihi)));
                $dateCell->getStyle()->getNumberFormat()->setFormatCode('DD.MM.YYYY');
            }
        }
        if (isset($colMap['izinKonusu']) && !empty($firm['profile']['izinKonusu'])) $worksheet->getCell("{$colMap['izinKonusu']}{$row}")->setValue($firm['profile']['izinKonusu']);
        if (isset($colMap['atiksuKurum']) && !empty($firm['profile']['atiksuKurum'])) $worksheet->getCell("{$colMap['atiksuKurum']}{$row}")->setValue($firm['profile']['atiksuKurum']);
        if (isset($colMap['atiksuTipi']) && !empty($firm['profile']['atiksuTipi'])) $worksheet->getCell("{$colMap['atiksuTipi']}{$row}")->setValue($firm['profile']['atiksuTipi']);
    }

    private function generateDefaultSpreadsheet($firms, $user)
    {
        $today = Carbon::today();
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);
        $evs = [];
        foreach ($firms as $f) $evs[$f['id']] = ComplianceEngine::evaluateFirm($f, $today, $cfg);
        $taskList = ComplianceEngine::taskList($firms, $evs);

        $spreadsheet = new Spreadsheet();
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Yapılacaklar');
        $headers1 = ['Durum', 'Tesis', 'Danışman', 'Konu', 'Madde', 'Son Yapılan', 'Son Tarih', 'Kalan Gün', 'Bekleyen / Uyarı'];
        $sheet1->fromArray($headers1, null, 'A1');

        $row = 2;
        foreach ($taskList as $item) {
            $f = $item[0]; $k = $item[1]; $e = $item[2];
            $it = $f['items'][$k] ?? [];
            $obl = ComplianceEngine::obl($k);
            $lastDone = isset($it['d']) ? Carbon::parse($it['d'])->format('d.m.Y') : '-';
            $due = $e['due'] ? Carbon::parse($e['due'])->format('d.m.Y') : '-';
            
            $sheet1->fromArray([
                ComplianceEngine::STATE_L[$e['state']], $f['name'], $f['consultant'] ?? '',
                $obl['n'], $e['label'], $lastDone, $due, $e['days'], implode(', ', $e['flags'])
            ], null, "A{$row}");
            $row++;
        }

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Tüm Takip');
        $headers2 = ['Tesis', 'Danışman', 'Sektör'];
        foreach (ComplianceEngine::keys() as $k) $headers2[] = ComplianceEngine::obl($k)['n'];
        $sheet2->fromArray($headers2, null, 'A1');

        $row = 2;
        foreach ($firms as $f) {
            $rowData = [$f['name'], $f['consultant'] ?? '', $f['sector'] ?? ''];
            foreach (ComplianceEngine::keys() as $k) {
                $e = $evs[$f['id']][$k];
                $daysText = $e['days'] !== null ? " ({$e['days']} gün)" : "";
                $rowData[] = ComplianceEngine::STATE_L[$e['state']] . $daysText;
            }
            $sheet2->fromArray($rowData, null, "A{$row}");
            $row++;
        }

        return $spreadsheet;
    }

    private function normalizeStr($str) 
    { 
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
            'note' => ['FIRMA DURUM', 'OZEL NOT', 'PROBLEM'], 
            'cedKarari' => ['CED KARARI'], 'cedTarihi' => ['CED GORUS TARIHI'], 
            'izinKonusu' => ['CEVRE IZIN KONUSU'], 'atiksuKurum' => ['ATIKSU GORUS KURUMU'], 'atiksuTipi' => ['ATIKSU TIPI']
        ];
        
        $obligations = ComplianceEngine::getAll();
        $itemsMapping = [];
        foreach ($obligations as $ob) {
            $k = $ob['k'];
            $n = mb_strtoupper($ob['n'], 'UTF-8');
            $s = mb_strtoupper($ob['s'] ?? $k, 'UTF-8');
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
}
