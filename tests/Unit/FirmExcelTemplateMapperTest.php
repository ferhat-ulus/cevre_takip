<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ExcelController;
use App\Http\Controllers\Api\ExportController;
use App\Models\Firm;
use App\Models\User;
use App\Services\FirmExcelTemplateMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class FirmExcelTemplateMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_template_input_columns_instead_of_formula_columns(): void
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        foreach ([
            'A2' => 'FİRMA',
            'E2' => 'KAPASİTE RAPORU',
            'F2' => 'KAPASİTE RAPORU BİTİŞ TARİHİ',
            'T2' => 'İZNE ESAS EMİSYON ÖLÇÜM TARİHİ',
            'U2' => 'EMİSYON ÖLÇÜM TARİHİ',
            'V2' => 'SONRAKİ EMİSYON ÖLÇÜMÜ',
            'W2' => 'EMİSYON RAPOR TARİHİ',
            'AP2' => 'SYD',
            'AQ2' => 'BELGE GEÇERLİLİK TARİHİ',
        ] as $cell => $value) {
            $worksheet->setCellValue($cell, $value);
        }

        $mapping = FirmExcelTemplateMapper::findColumnMappings($worksheet, 2);

        $this->assertSame('A', $mapping['firma']);
        $this->assertSame('E', $mapping['items']['kapasite']);
        $this->assertSame('T', $mapping['items']['izneEsasEmisyonOlcum']);
        $this->assertSame('U', $mapping['items']['emisyonOlcum']);
        $this->assertSame('W', $mapping['items']['emisyonRapor']);
        $this->assertSame('AP', $mapping['items']['syd']);
        $this->assertNull($mapping['items']['ilUygunluk']);
    }

    public function test_maps_the_explicit_permit_basis_emission_report_header(): void
    {
        $worksheet = (new Spreadsheet)->getActiveSheet();
        $worksheet->setCellValue('W2', 'İZNE ESAS EMİSYON RAPOR TARİHİ');

        $mapping = FirmExcelTemplateMapper::findColumnMappings($worksheet, 2);

        $this->assertSame('W', $mapping['items']['emisyonRapor']);
    }

    public function test_maps_confirmation_emission_report_header_separately(): void
    {
        $worksheet = (new Spreadsheet)->getActiveSheet();
        $worksheet->setCellValue('X2', 'TEYİT EMİSYON RAPOR TARİHİ');

        $mapping = FirmExcelTemplateMapper::findColumnMappings($worksheet, 2);

        $this->assertSame('X', $mapping['items']['emisyonTeyitRaporu']);
    }

    public function test_maps_plain_emission_report_date_to_confirmation_when_permit_report_has_its_own_column(): void
    {
        $worksheet = (new Spreadsheet)->getActiveSheet();
        $worksheet->setCellValue('X2', 'İZNE ESAS EMİSYON RAPOR TARİHİ');
        $worksheet->setCellValue('Y2', 'EMİSYON RAPOR TARİHİ');

        $mapping = FirmExcelTemplateMapper::findColumnMappings($worksheet, 2);

        $this->assertSame('X', $mapping['items']['emisyonRapor']);
        $this->assertSame('Y', $mapping['items']['emisyonTeyitRaporu']);
    }

    public function test_import_treats_infirmary_absence_as_out_of_scope(): void
    {
        $controller = new ExcelController;
        $parseCell = new \ReflectionMethod($controller, 'parseExcelCell');
        $parseCell->setAccessible(true);

        $unread = [];
        $arguments = ['REVİR YOK', 'tayp', null, 3, 'AS'];
        $arguments[2] = &$unread;

        $this->assertSame(['s' => 'muaf', 'v' => 'REVİR YOK'], $parseCell->invokeArgs($controller, $arguments));
        $this->assertSame([], $unread);
    }

    public function test_replaces_external_dropdown_references_with_local_named_ranges(): void
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $listWorksheet = $spreadsheet->createSheet();
        $listWorksheet->setTitle('Çoktan Seçmeli Veri Tablosu');
        $listWorksheet->setCellValue('A1', 'KAPASİTE RAPORU');
        $listWorksheet->setCellValue('A2', 'KAPSAMDIŞI');
        $listWorksheet->setCellValue('A3', "\u{0053}\u{00FC}re\u{00E7}te");
        $listWorksheet->setCellValue('A4', 'A');

        $validation = $worksheet->getCell('E3')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setFormula1("'[old-template.xlsx]Çoktan Seçmeli Veri Tablosu'!#REF!");
        $worksheet->setDataValidation('E3:E10', $validation);

        FirmExcelTemplateMapper::repairExternalDropdowns($spreadsheet, $worksheet);

        $this->assertSame('=CevreTakipOptions_A', $worksheet->getCell('E3')->getDataValidation()->getFormula1());
        $this->assertSame('$A$2:$A$3', $spreadsheet->getNamedRange('CevreTakipOptions_A')->getRange());
        $this->assertSame('A', $listWorksheet->getCell('A3')->getValue());
        $this->assertNull($listWorksheet->getCell('A4')->getValue());

        $path = tempnam(sys_get_temp_dir(), 'template-test');
        try {
            (new Xlsx($spreadsheet))->save($path);
            $roundTrip = IOFactory::load($path);
            $this->assertSame(
                '=CevreTakipOptions_A',
                $roundTrip->getSheet(0)->getCell('E3')->getDataValidation()->getFormula1()
            );
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_import_treats_legacy_status_as_missing_and_warns(): void
    {
        $controller = new ExcelController;
        $parseCell = new \ReflectionMethod($controller, 'parseExcelCell');
        $parseCell->setAccessible(true);

        $unread = [];
        $arguments = ["\u{0053}\u{00FC}re\u{00E7}te", 'kapasite', null, 3, 'E'];
        $arguments[2] = &$unread;

        $this->assertSame(['s' => null, '_clear' => true], $parseCell->invokeArgs($controller, $arguments));
        $this->assertSame('Bu değer veri yok kabul edilir; mevcut kayıt temizlenecek.', $unread[0]['message']);
    }

    public function test_importing_legacy_status_clears_the_existing_item_value(): void
    {
        $firm = Firm::create([
            'id' => 'clear-test',
            'data' => [
                'name' => 'Test Firma',
                'items' => ['kapasite' => ['s' => 'tarih', 'd' => '2025-01-01', 'note' => 'Eski not']],
                'profile' => [],
            ],
        ]);
        $user = User::create([
            'username' => 'clear-test-user',
            'name' => 'Test Kullanıcı',
            'email' => 'clear-test@example.com',
            'role' => 'admin',
            'password' => 'password',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'import-test');
        try {
            (new Xlsx(new Spreadsheet))->save($path);
            $file = new UploadedFile($path, 'import.xlsx', null, null, true);
            $controller = new ExcelController;
            $commit = new \ReflectionMethod($controller, 'commitExcel');
            $commit->setAccessible(true);
            $commit->invoke($controller, $file, $user, [[
                'selected' => true,
                'is_new' => false,
                'name' => 'Test Firma',
                'items' => ['kapasite' => ['s' => null, '_clear' => true]],
                'item_selections' => ['kapasite' => true],
                'profile' => [],
                'note' => '',
                'consultant' => '',
                'sector' => '',
            ]]);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $item = $firm->fresh()->data['items']['kapasite'];
        $this->assertNull($item['s']);
        $this->assertArrayNotHasKey('d', $item);
        $this->assertArrayNotHasKey('note', $item);
        $this->assertArrayNotHasKey('_clear', $item);
    }

    public function test_export_clears_legacy_status_without_overwriting_formula_columns(): void
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setCellValue('E2', 'KAPASİTE RAPORU');
        $worksheet->setCellValue('F2', 'KAPASİTE RAPORU BİTİŞ TARİHİ');
        $worksheet->setCellValue('K2', 'ÇED KARARI');
        $worksheet->setCellValue('L2', 'ÇED GÖRÜŞ TARİHİ');
        $worksheet->setCellValue('E3', "\u{0053}\u{00FC}re\u{00E7}te");
        $worksheet->setCellValue('F3', '=E3+720');
        $worksheet->setCellValue('K3', "\u{0053}\u{00FC}re\u{00E7}te");
        $worksheet->setCellValue('L3', '2025-01-01');

        $controller = new ExportController;
        $writeRow = new \ReflectionMethod($controller, 'writeFirmDataToRow');
        $writeRow->setAccessible(true);
        $columnMap = FirmExcelTemplateMapper::findColumnMappings($worksheet, 2);
        $writeRow->invoke($controller, $worksheet, 3, [
            'items' => ['kapasite' => ['s' => 'surec']],
            'profile' => [
                'cedKarari' => "\u{0053}\u{00FC}re\u{00E7}te",
                'cedTarihi' => '2025-01-01',
            ],
        ], $columnMap);

        $this->assertNull($worksheet->getCell('E3')->getValue());
        $this->assertSame('=E3+720', $worksheet->getCell('F3')->getValue());
        $this->assertNull($worksheet->getCell('K3')->getValue());
        $this->assertNull($worksheet->getCell('L3')->getValue());
    }
}
