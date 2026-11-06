<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class FirmExcelTemplateMapper
{
    private const ITEM_HEADERS = [
        'kapasite' => ['KAPASITE RAPORU'],
        'police' => ['ATIK POLICE TARIHI'],
        'izin' => ['CEVRE IZIN/LISANS GORUS TARIHI'],
        'izneEsasEmisyonOlcum' => ['IZNE ESAS EMISYON OLCUM TARIHI'],
        'emisyonOlcum' => ['EMISYON OLCUM TARIHI'],
        'emisyonRapor' => ['IZNE ESAS EMISYON RAPOR TARIHI', 'EMISYON RAPOR TARIHI'],
        'emisyonTeyitRaporu' => ['TEYIT EMISYON RAPOR TARIHI', 'EMISYON TEYIT RAPOR TARIHI'],
        'atiksuIzleme' => ['ATIKSU IC IZLEME TARIHI (EN SON YAPILAN)'],
        'aracLisans' => ['ARAC ATIK TASIMA LISANSI TARIHI'],
        'eayp' => ['EAYP ONAY TARIHI'],
        'syd' => ['SYD'],
        'tayp' => ['TIBBI ATIK YONETIM PLANI'],
        'sifirAtik' => ['SIFIR ATIK BELGE TARIHI'],
        'madenAtik' => ['MADEN ATIK YONETIM PLANI TARIHI'],
        'kutleDenge' => ['KUTLE DENGE BILDIRIM TARIHI'],
        'bekra' => ['BEKRA HESAPLAMA TARIHI'],
        'ekomvet' => ['EKOMVET KAYIT/TEST TARIHI (EN SON YAPILAN)'],
        'kstk' => ['KSTK KAYIT TARIHI (EN SON YAPILAN)'],
        'gekap' => ['GEKAP BEYANI TARIHI'],
        'moyden' => ['MOYDEN'],
        'icTetkik' => ['IC TETKIK RAPORU'],
        'egitim' => ['EGITIM TARIHI'],
        'atiksuGorus' => ['ATIKSU GORUSU TARIHI'],
    ];

    private const DROPDOWN_LIST_COLUMNS = [
        'E' => 'A',
        'K' => 'B',
        'M' => 'C',
        'N' => 'D',
        'AE' => 'E',
        'H' => 'F',
        'BE' => 'G',
        'AM' => 'H',
        'AP' => 'I',
        'AS' => 'J',
        'T' => 'K',
        'U' => 'K',
        'X' => 'L',
        'Y' => 'L',
        'AB' => 'M',
        'AH' => 'N',
        'AI' => 'O',
        'AJ' => 'P',
        'BK' => 'Q',
        'BN' => 'R',
        'AV' => 'S',
        'AY' => 'T',
        'BP' => 'U',
        'BB' => 'V',
        'BS' => 'W',
        'BV' => 'X',
        'BH' => 'F',
    ];

    public static function findColumnMappings(Worksheet $worksheet, int $headerRow): array
    {
        $headers = [];
        $highestColumn = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        for ($index = 1; $index <= $highestColumn; $index++) {
            $column = Coordinate::stringFromColumnIndex($index);
            $value = self::normalize($worksheet->getCell("{$column}{$headerRow}")->getValue());
            if ($value !== '') {
                $headers[$column] = $value;
            }
        }

        $fieldSearches = [
            'firma' => ['FIRMA'],
            'danisman' => ['DANISMAN'],
            'sektor' => ['SEKTOR'],
            'note' => ['FIRMA DURUM', 'OZEL NOT', 'PROBLEM'],
            'cedKarari' => ['CED KARARI'],
            'cedTarihi' => ['CED GORUS TARIHI'],
            'izinKonusu' => ['CEVRE IZIN KONUSU'],
            'atiksuKurum' => ['ATIKSU GORUS KURUMU'],
            'atiksuTipi' => ['ATIKSU TIPI'],
        ];

        $result = ['items' => []];
        foreach ($fieldSearches as $key => $searches) {
            $result[$key] = self::findColumn($headers, $searches);
        }

        foreach (ComplianceEngine::getAll() as $obligation) {
            $key = $obligation['k'];
            if (($obligation['kind'] ?? null) === 'linked_izin') {
                $result['items'][$key] = null;
                continue;
            }

            $searches = array_merge(
                self::ITEM_HEADERS[$key] ?? [],
                [$obligation['n'], $obligation['s'] ?? $key, $key]
            );
            $result['items'][$key] = self::findColumn($headers, $searches);
        }

        $permitReportColumn = $result['items']['emisyonRapor'] ?? null;
        $confirmationReportColumn = $result['items']['emisyonTeyitRaporu'] ?? null;
        if ($confirmationReportColumn === null || $confirmationReportColumn === $permitReportColumn) {
            foreach ($headers as $column => $header) {
                if ($header === 'EMISYON RAPOR TARIHI' && $column !== $permitReportColumn) {
                    $result['items']['emisyonTeyitRaporu'] = $column;
                    break;
                }
            }
        }

        return $result;
    }

    public static function repairExternalDropdowns(Spreadsheet $spreadsheet, Worksheet $worksheet): void
    {
        $listWorksheet = $spreadsheet->getSheetByName('Çoktan Seçmeli Veri Tablosu');
        if ($listWorksheet === null) {
            throw new RuntimeException('Excel şablonunda açılır liste seçeneklerini içeren sayfa bulunamadı.');
        }

        self::removeLegacyStatusOptions($spreadsheet);

        $listLastRows = [];
        foreach ($worksheet->getDataValidationCollection() as $range => $validation) {
            $formula = $validation->getFormula1();
            if (!preg_match('/#REF!|\[[^\]]+\.xlsx\]/i', $formula)) {
                continue;
            }

            $worksheet->setDataValidation($range, null);
            foreach (preg_split('/\s+/', trim($range)) as $rangePart) {
                if ($rangePart === '') {
                    continue;
                }

                [$start, $end] = array_pad(explode(':', $rangePart, 2), 2, null);
                [$startColumn, $startRow] = Coordinate::coordinateFromString($start);
                [$endColumn, $endRow] = Coordinate::coordinateFromString($end ?? $start);
                $startIndex = Coordinate::columnIndexFromString($startColumn);
                $endIndex = Coordinate::columnIndexFromString($endColumn);

                for ($index = $startIndex; $index <= $endIndex; $index++) {
                    $column = Coordinate::stringFromColumnIndex($index);
                    $listColumn = self::DROPDOWN_LIST_COLUMNS[$column] ?? null;
                    if ($listColumn === null) {
                        throw new RuntimeException("{$column} sütunundaki açılır listenin yerel seçenek eşlemesi bulunamadı.");
                    }

                    if (!isset($listLastRows[$listColumn])) {
                        $lastRow = 1;
                        for ($row = 2; $row <= $listWorksheet->getHighestDataRow(); $row++) {
                            $value = $listWorksheet->getCell("{$listColumn}{$row}")->getValue();
                            if ($value !== null && $value !== '') {
                                $lastRow = $row;
                            }
                        }
                        if ($lastRow === 1) {
                            throw new RuntimeException("Excel şablonundaki {$listColumn} sütununda açılır liste seçeneği bulunamadı.");
                        }
                        $listLastRows[$listColumn] = $lastRow;
                    }

                    $name = "CevreTakipOptions_{$listColumn}";
                    $spreadsheet->addNamedRange(new NamedRange(
                        $name,
                        $listWorksheet,
                        '$' . $listColumn . '$2:$' . $listColumn . '$' . $listLastRows[$listColumn]
                    ));

                    $columnRange = $index === $startIndex && $index === $endIndex
                        ? $rangePart
                        : "{$column}{$startRow}:{$column}{$endRow}";
                    $repairedValidation = clone $validation;
                    $repairedValidation->setFormula1("={$name}");
                    $worksheet->setDataValidation($columnRange, $repairedValidation);
                }
            }
        }
    }

    public static function removeLegacyStatusOptions(Spreadsheet $spreadsheet): void
    {
        $listWorksheet = $spreadsheet->getSheetByName('Çoktan Seçmeli Veri Tablosu');
        if ($listWorksheet === null) {
            throw new RuntimeException('Excel şablonunda açılır liste seçeneklerini içeren sayfa bulunamadı.');
        }

        $highestRow = $listWorksheet->getHighestDataRow();
        $listColumns = array_unique(array_values(self::DROPDOWN_LIST_COLUMNS));

        foreach ($listColumns as $column) {
            $values = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $value = $listWorksheet->getCell("{$column}{$row}")->getValue();
                if ($value === null || $value === '' || self::normalize((string) $value) === 'SURECTE') {
                    continue;
                }
                $values[] = $value;
            }

            for ($row = 2; $row <= $highestRow; $row++) {
                $listWorksheet->setCellValue("{$column}{$row}", $values[$row - 2] ?? null);
            }
        }
    }

    private static function findColumn(array $headers, array $searches): ?string
    {
        foreach ($searches as $search) {
            $normalizedSearch = self::normalize($search);
            foreach ($headers as $column => $header) {
                if ($header === $normalizedSearch) {
                    return $column;
                }
            }
        }

        $excludedWords = ['BITIS', 'KALAN', 'SONRAKI', 'GUNCELLEME', 'SON BASVURU'];
        foreach ($searches as $search) {
            $normalizedSearch = self::normalize($search);
            foreach ($headers as $column => $header) {
                if (!str_contains($header, $normalizedSearch)) {
                    continue;
                }

                $excluded = false;
                foreach ($excludedWords as $word) {
                    if (str_contains($header, $word)) {
                        $excluded = true;
                        break;
                    }
                }

                if (!$excluded || in_array($normalizedSearch, ['FIRMA DURUM', 'OZEL NOT', 'PROBLEM'], true)) {
                    return $column;
                }
            }
        }

        return null;
    }

    private static function normalize(?string $value): string
    {
        $value = trim($value ?? '');
        $value = str_replace(
            ['İ', 'ı', 'Ş', 'ş', 'Ğ', 'ğ', 'Ü', 'ü', 'Ö', 'ö', 'Ç', 'ç'],
            ['I', 'i', 'S', 's', 'G', 'g', 'U', 'u', 'O', 'o', 'C', 'c'],
            $value
        );
        $value = mb_strtoupper($value, 'UTF-8');
        $value = preg_replace('/[^A-Z0-9\s\/]/', '', $value);

        return preg_replace('/\s+/', ' ', trim($value));
    }
}
