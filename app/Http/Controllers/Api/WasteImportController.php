<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class WasteImportController extends Controller
{
    private const FIELDS = [
        'waste_date', 'waste_name', 'waste_code', 'tkn', 'tdn', 'plate', 'producer_name', 'processor_name',
        'district', 'processor_vkn', 'producer_vkn', 'un_number', 'adr_name', 'adr_class',
        'observed_amount', 'address', 'period',
    ];

    public function preview(Request $request)
    {
        $request->validate(['excel' => 'required|file|mimes:xlsx|max:5120']);
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        $book = $reader->load($request->file('excel')->getPathname());
        $rows = [];
        $invalid = [];
        foreach ($book->getAllSheets() as $sheet) {
            if ($sheet->getTitle() !== 'ATIK TAŞIMA' && !preg_match('/^\d{4}-\d{2}$/', $sheet->getTitle())) continue;
            if ($sheet->getCell('A1')->getValue() !== 'ATIK ALIM TARİHİ' || $sheet->getCell('C1')->getValue() !== 'ATIK KODU'
                || $sheet->getCell('Q1')->getValue() !== 'PERİYOT') {
                return response()->json(['message' => 'Excel başlıkları taşıma şablonuyla uyuşmuyor.'], 422);
            }
            foreach ($sheet->getRowIterator(2, max(2, $sheet->getHighestDataRow())) as $line) {
                $number = $line->getRowIndex();
                $cells = $sheet->rangeToArray('A'.$number.':Q'.$number, null, false, false)[0];
                // Read the calculated value saved by Excel without evaluating imported formulas.
                foreach (['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q'] as $index => $column) {
                    $cell = $sheet->getCell($column.$number);
                    if ($cell->isFormula()) $cells[$index] = $cell->getOldCalculatedValue();
                }
                if (!filled($cells[0])) continue;
                $row = array_combine(self::FIELDS, array_map(fn ($value) => filled($value) ? trim((string) $value) : null, $cells));
                $rawDate = $row['waste_date'];
                $row['waste_date'] = is_numeric($rawDate) && (float) $rawDate >= 1 && (float) $rawDate <= 2958465
                    ? Date::excelToDateTimeObject((float) $rawDate)->format('Y-m-d') : substr($rawDate, 0, 10);
                $row['waste_code'] = str_pad(preg_replace('/[\s*]+/u', '', $row['waste_code'] ?? ''), 6, '0', STR_PAD_LEFT);
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['waste_date']);
                $row['_source'] = $sheet->getTitle().'!'.$number;
                if (!$date || $date->format('Y-m-d') !== $row['waste_date'] || !preg_match('/^[0-9]{6}$/D', $row['waste_code'])
                    || $row['waste_code'] === '000000' || !filled($row['producer_name']) || !filled($row['processor_name'])) {
                    $invalid[] = ['source' => $row['_source'], 'status' => 'Eksik veya geçersiz alan'];
                    continue;
                }
                $rows[] = $row;
                if (count($rows) + count($invalid) > 5000) return response()->json(['message' => 'Bir seferde en çok 5.000 satır aktarılabilir.'], 422);
            }
        }
        if (!$rows && !$invalid) return response()->json(['message' => 'Taşıma sayfalarında tarihli satır bulunamadı.'], 422);
        [$new, $report] = $this->compare($rows);
        $token = Str::random(40);
        $request->session()->put('waste_import_preview', ['token' => $token, 'rows' => $new]);
        return response()->json(['token' => $token, 'new' => count($new), 'report' => [...$report, ...$invalid]]);
    }

    public function commit(Request $request)
    {
        $request->validate(['token' => 'required|string']);
        $preview = $request->session()->get('waste_import_preview');
        abort_unless($preview && hash_equals($preview['token'], $request->input('token')), 422, 'Önce Excel önizlemesi oluşturun.');
        $count = DB::transaction(function () use ($preview) {
            [$new] = $this->compare($preview['rows']);
            foreach (array_chunk($new, 40) as $chunk) {
                DB::table('waste_imports')->insert(array_map(fn ($row) => array_intersect_key($row, array_flip(self::FIELDS)), $chunk));
            }
            return count($new);
        });
        $request->session()->forget('waste_import_preview');
        return response()->json(['inserted' => $count]);
    }

    private function compare(array $rows): array
    {
        $existing = DB::table('waste_imports')->get(self::FIELDS);
        $exact = $references = $possible = [];
        $normalize = fn ($value) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
        $index = function (array $row) use ($normalize, &$exact, &$references, &$possible) {
            $day = substr($row['waste_date'], 0, 10);
            $code = str_pad(preg_replace('/\s|\*/u', '', (string) $row['waste_code']), 6, '0', STR_PAD_LEFT);
            $signature = json_encode([$day, $code, ...array_map(fn ($key) => $normalize($row[$key]), array_slice(self::FIELDS, 3))], JSON_THROW_ON_ERROR);
            $key = json_encode([$day, $code, $normalize($row['producer_name']), $normalize($row['processor_name'])], JSON_THROW_ON_ERROR);
            $refs = array_filter(array_map(fn ($field) => filled($row[$field]) ? $field.':'.trim($row[$field]) : null, ['tkn','tdn']));
            $status = isset($exact[$signature]) ? 'Eşleşen' : (array_intersect_key($references, array_flip($refs)) || (!$refs && isset($possible[$key])) ? 'Çelişkili' : 'Yeni');
            $exact[$signature] = $possible[$key] = true;
            foreach ($refs as $reference) $references[$reference] = true;
            return $status;
        };
        foreach ($existing as $record) $index((array) $record);
        $new = $report = [];
        foreach ($rows as $row) {
            $status = $index($row);
            if ($status === 'Yeni') $new[] = $row;
            $report[] = ['source' => $row['_source'], 'status' => $status, 'date' => $row['waste_date'], 'code' => $row['waste_code'], 'producer' => $row['producer_name']];
        }
        return [$new, $report];
    }
}
