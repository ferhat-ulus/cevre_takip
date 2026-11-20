<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WasteTransportController extends Controller
{
    private ?array $savedFacilities = null;

    private function facilityFingerprint(string $name, ?string $address): string
    {
        $normalize = fn ($value) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value)));
        return hash('sha256', $normalize($name).'|'.$normalize($address));
    }

    private function savedFacility(string $role, string $name, ?string $address): ?array
    {
        if ($this->savedFacilities === null) {
            $this->savedFacilities = [];
            $cards = DB::table('waste_facilities as f')->join('waste_companies as c', 'c.id', '=', 'f.company_id')
                ->select('f.*', 'c.vkn', 'c.name as company_name')->get()->keyBy('id');
            foreach ($cards as $card) {
                $this->savedFacilities[$card->role][$this->facilityFingerprint($card->name, $card->address)] = $card;
            }
            foreach (DB::table('waste_facility_aliases')->get() as $alias) {
                $this->savedFacilities[$alias->role][$alias->fingerprint] = $cards[$alias->facility_id];
            }
            foreach ($cards->groupBy(fn ($card) => $card->role.':'.$this->facilityFingerprint($card->name, null)) as $candidates) {
                if ($candidates->count() !== 1) continue;
                $card = $candidates->first();
                $this->savedFacilities[$card->role][$this->facilityFingerprint($card->name, null)] ??= $card;
            }
        }
        $card = $this->savedFacilities[$role][$this->facilityFingerprint($name, $address)] ?? null;
        if (!$card) return null;
        return [...(array) $card, 'card_id' => $card->id, 'id' => 'card-'.$card->id];
    }

    private function facilityAddress(string $role, array $facility, ?string $district): ?string
    {
        if ($role === 'processor' && filled($district) && filled($facility['district'] ?? null)) {
            $cardDistrict = strtolower(Str::ascii($facility['district'], 'tr'));
            $districtParts = preg_split('/[^a-z]+/', strtolower(Str::ascii($district, 'tr')));
            if (!in_array($cardDistrict, $districtParts, true)) return null;
        }

        return $facility['address'] ?? null;
    }

    public function saveFacilityCard(Request $request)
    {
        $data = $request->validate([
            'role' => 'required|in:producer,processor', 'card_id' => 'nullable|integer|exists:waste_facilities,id',
            'company_name' => 'required|string|max:500', 'vkn' => 'required|regex:/^[0-9]{10}$/',
            'name' => 'required|string|max:500', 'address' => 'nullable|string|max:1000',
            'district' => 'nullable|string|max:255', 'ckn' => 'nullable|regex:/^[0-9]{1,20}$/',
            'record_ids' => 'required|array|min:1', 'record_ids.*' => 'integer|exists:waste_imports,id',
        ]);
        DB::transaction(function () use ($data) {
            $role = $data['role'];
            $addressField = $role === 'producer' ? 'address' : 'processor_address';
            $records = DB::table('waste_imports')->whereIn('id', $data['record_ids'])->get();
            $fingerprints = $records->map(fn ($record) => $this->facilityFingerprint($record->{$role.'_name'}, $record->{$addressField}));
            if (!empty($data['card_id'])) {
                abort_unless(DB::table('waste_facilities')->where('id', $data['card_id'])->where('role', $role)->exists(), 404);
            }
            $duplicates = DB::table('waste_facilities')->where('role', $role)->where('name', $data['name'])
                ->when(!empty($data['card_id']), fn ($q) => $q->where('id', '!=', $data['card_id']))->exists();
            $aliasConflict = DB::table('waste_facility_aliases')->where('role', $role)->whereIn('fingerprint', $fingerprints)
                ->when(!empty($data['card_id']), fn ($q) => $q->where('facility_id', '!=', $data['card_id']))->exists();
            if ($duplicates || $aliasConflict) {
                throw \Illuminate\Validation\ValidationException::withMessages(['name' => 'Bu tesis adı veya yazım biçimi başka bir karta bağlı.']);
            }
            $companyId = DB::table('waste_companies')->where('vkn', $data['vkn'])->value('id');
            if (!$companyId) {
                $companyId = DB::table('waste_companies')->insertGetId(['vkn' => $data['vkn'], 'name' => $data['company_name']]);
            } else {
                DB::table('waste_companies')->where('id', $companyId)->update(['name' => $data['company_name']]);
            }
            $card = ['company_id' => $companyId, 'role' => $role, 'name' => $data['name'], 'address' => $data['address'] ?? null,
                'district' => $data['district'] ?? null, 'ckn' => $data['ckn'] ?? null];
            $id = $data['card_id'] ?? DB::table('waste_facilities')->insertGetId($card);
            if (!empty($data['card_id'])) DB::table('waste_facilities')->where('id', $id)->update($card);
            foreach ($fingerprints->unique() as $fingerprint) {
                DB::table('waste_facility_aliases')->updateOrInsert(['role' => $role, 'fingerprint' => $fingerprint], ['facility_id' => $id]);
            }
        });
        return redirect()->route($data['role'] === 'producer' ? 'waste.producers' : 'waste.processors');
    }
    private const EXCEL_COLUMNS = [
        'waste_date' => ['ATIK ALIM TARİHİ', 14],
        'waste_name' => ['ATIK İSMİ', 37.45],
        'waste_code' => ['ATIK KODU', 14],
        'tkn' => ['TKN', 24.45],
        'tdn' => ['TDN', 14.82],
        'plate' => ['ARAÇ PLAKASI', 14],
        'producer_name' => ['YÜKLEME YAPILACAK FİRMA', 41.18],
        'processor_name' => ['BOŞALTMA YAPILACAK FİRMA İSMİ', 54.18],
        'district' => ['BOŞALTMA İŞLEMİ YAPILACAK YER İLÇE', 14],
        'processor_vkn' => ['BOŞALTMA İŞLEMİ YAPILAN FİRMA VERGİ NO', 14.82],
        'producer_vkn' => ['GÖNDERİM YAPAN FİRMA VERGİ NO', 16.18],
        'un_number' => ['ATANACAK UN NUMARASI', 14.18],
        'adr_name' => ['İSİM VE TANIM', 30.82],
        'adr_class' => ['SINIF', 14],
        'observed_amount' => ['ATIK MİKTARI (KG, TAHMİNİ)', 16],
        'address' => ['ALIM YAPILAN ADRES', 48.45],
        'period' => ['PERİYOT', 13],
    ];

    public function exportExcel(Request $request)
    {
        $filters = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $months = DB::table('waste_imports')
            ->where(fn ($query) => $query->whereNull('transport_status')->orWhere('transport_status', '!=', 'cancelled'))
            ->when(!empty($filters['from']), fn ($query) => $query->whereDate('waste_date', '>=', $filters['from']))
            ->when(!empty($filters['to']), fn ($query) => $query->whereDate('waste_date', '<=', $filters['to']))
            ->orderBy('waste_date')->orderBy('id')->get()
            ->groupBy(fn ($record) => substr($record->waste_date, 0, 7));

        $book = new Spreadsheet();
        $book->getDefaultStyle()->getFont()->setSize(9);
        foreach ($months as $month => $records) {
            $sheet = $month === $months->keys()->first() ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($month);
            $column = 'A';
            foreach (self::EXCEL_COLUMNS as [$heading, $width]) {
                $sheet->setCellValue($column.'1', $heading);
                $sheet->getColumnDimension($column)->setWidth($width);
                $color = match ($column) {
                    'E', 'F' => 'A5B6CB',
                    'L', 'M', 'N' => 'E6B9B8',
                    default => 'D7E4BD',
                };
                $sheet->getStyle($column.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                $column++;
            }
            $sheet->getStyle('A1:Q1')->getFont()->setBold(true);
            $sheet->getStyle('A1:Q1')->getAlignment()->setWrapText(true);
            $sheet->getRowDimension(1)->setRowHeight(36);
            $sheet->getDefaultRowDimension()->setRowHeight(34);
            $sheet->freezePane('A2');

            $line = 2;
            foreach ($records as $record) {
                foreach (['producer', 'processor'] as $role) {
                    $facility = $this->confirmedFacility($role, (string) $record->{$role.'_name'}, $role === 'producer' ? $record->address : $record->processor_address);
                    if ($facility) {
                        $record->{$role.'_name'} = $facility['name'];
                        if ($facility['vkn'] !== null) $record->{$role.'_vkn'} = $facility['vkn'];
                    }
                }
                $column = 'A';
                foreach (self::EXCEL_COLUMNS as $key => $definition) {
                    $value = $record->{$key};
                    $cell = $column++.$line;
                    if ($value === null || $value === '') continue;
                    if ($key === 'waste_date') {
                        $sheet->setCellValue($cell, Date::PHPToExcel(new \DateTimeImmutable(substr($value, 0, 10))));
                    } elseif ($key === 'observed_amount' && preg_match('/^\d+(\.\d+)?$/D', $value)) {
                        $sheet->setCellValue($cell, (float) $value);
                    } else {
                        $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                    }
                }
                $line++;
            }
            $end = $line - 1;
            $sheet->getStyle('A2:A'.$end)->getNumberFormat()->setFormatCode('dd.mm.yyyy');
            foreach (['C', 'D', 'E', 'J', 'K', 'L'] as $column) {
                $sheet->getStyle($column.'2:'.$column.$end)->getNumberFormat()->setFormatCode('@');
            }
            foreach (['B', 'G', 'H', 'M', 'P'] as $column) {
                $sheet->getStyle($column.'2:'.$column.$end)->getAlignment()->setWrapText(true);
            }
            $sheet->setAutoFilter('A1:Q'.$end);
        }
        if ($months->isEmpty()) {
            $book->getActiveSheet()->setTitle('Kayıt yok')->setCellValue('A1', 'Bu tarih aralığında taşıma kaydı yok.');
        }
        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            'Atik-Takip.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'],
        );
    }

    private const ADR_CATALOG = [
        '1373' => ['un_number' => '1373', 'adr_name' => 'LİFLER veya KUMAŞLAR, HAYVANSAL veya BİTKİSEL veya SENTETİK, B.B.B., yağlı', 'adr_class' => '4.2'],
        '3077' => ['un_number' => '3077', 'adr_name' => 'ÇEVREYE ZARARLI MADDE, KATI, B.B.B.', 'adr_class' => '9'],
        '3082' => ['un_number' => '3082', 'adr_name' => 'ÇEVREYE ZARARLI MADDE, SIVI, B.B.B.', 'adr_class' => '9'],
        '3249' => ['un_number' => '3249', 'adr_name' => 'İLAÇ, KATI, ZEHİRLİ, B.B.B.', 'adr_class' => '6.1'],
        '3506' => ['un_number' => '3506', 'adr_name' => 'ÜRETİLMİŞ NESNELERİN İÇERDİĞİ CIVA', 'adr_class' => '8'],
        '3509' => ['un_number' => '3509', 'adr_name' => 'AMBALAJLAR, ISKARTA, BOŞ, TEMİZLENMEMİŞ', 'adr_class' => '9'],
    ];

    private function adrForWasteCode(string $code, ?int $excludeId): array
    {
        $candidates = DB::table('waste_imports')->where('waste_code', $code)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->select('un_number', 'adr_name', 'adr_class')->distinct()->get();
        $options = [];
        foreach ($candidates as $candidate) {
            $number = preg_replace('/^UN\s*/i', '', trim((string) $candidate->un_number));
            if (preg_match('/^[0-9]{4}(?:\s*\/\s*[0-9]{4})+$/D', $number)) {
                return ['un_number' => null, 'adr_name' => null, 'adr_class' => null];
            }
            if (!preg_match('/^[0-9]{4}$/', $number)) continue;
            $data = self::ADR_CATALOG[$number] ?? null;
            if (!$data) return ['un_number' => null, 'adr_name' => null, 'adr_class' => null];
            $options[json_encode($data, JSON_THROW_ON_ERROR)] = $data;
        }
        if (count($options) === 1) {
            $entry = reset($options);
            if ($entry['adr_name'] !== '' && $entry['adr_class'] !== '') return $entry;
        }
        return ['un_number' => null, 'adr_name' => null, 'adr_class' => null];
    }

    public function updateAdrAccess(Request $request)
    {
        $input = $request->validate(['user_id' => 'required|integer|exists:users,id', 'enabled' => 'required|boolean']);
        DB::table('users')->where('id', $input['user_id'])->update(['can_classify_waste_adr' => $input['enabled']]);
        return redirect()->route('waste.records');
    }

    public function classifyAdr(Request $request, int $id)
    {
        abort_unless($request->user()->can_classify_waste_adr, 403);
        $input = $request->validate([
            'un_number' => ['required', 'regex:/^[0-9]{4}$/D'],
            'waste_code' => 'required|string',
            'approve_existing' => 'sometimes|boolean',
            'manual_entry' => 'sometimes|boolean',
            'manual_adr_name' => 'nullable|string|max:500',
            'manual_adr_class' => 'nullable|in:1,1.1,1.2,1.3,1.4,1.5,1.6,2,2.1,2.2,2.3,3,4.1,4.2,4.3,5.1,5.2,6.1,6.2,7,8,9',
            'adr_reference' => 'nullable|string|max:500',
        ]);
        DB::transaction(function () use ($request, $id, $input) {
            $query = DB::table('waste_imports')->where('id', $id);
            $record = (clone $query)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if($record->transport_status === 'cancelled', 409, 'Gerçekleşmeyen taşıma için ADR onayı verilemez.');
            if ($record->waste_code !== $input['waste_code']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['un_number' => 'Atık kodu değişmiş. Kaydı yenileyip tekrar seçin.']);
            }
            $approveExisting = $input['approve_existing'] ?? false;
            if ($approveExisting && ((string) $record->un_number !== $input['un_number'] || !filled($record->adr_name) || !filled($record->adr_class))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['un_number' => 'ADR bilgileri eksik veya değişmiş. Satırı yenileyip kontrol edin.']);
            }
            $data = self::ADR_CATALOG[$input['un_number']] ?? null;
            if (!$data) {
                if ($approveExisting || !($input['manual_entry'] ?? false) || $input['un_number'] === '0000'
                    || !filled($input['manual_adr_name'] ?? null) || !filled($input['manual_adr_class'] ?? null)
                    || !filled($input['adr_reference'] ?? null)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'un_number' => 'Katalog dışında bir UN için uygun sevkiyat adını, ADR sınıfını ve doğrulama kaynağını girin.',
                    ]);
                }
                $data = ['un_number' => $input['un_number'], 'adr_name' => $input['manual_adr_name'],
                    'adr_class' => $input['manual_adr_class'], 'adr_reference' => $input['adr_reference']];
            }
            $data += ['adr_reference' => null];
            $data['adr_approved_at'] = now('Europe/Istanbul')->format('Y-m-d H:i:s');
            $data['adr_approved_by'] = $request->user()->id;
            $changes = [];
            foreach ($data as $key => $value) {
                if ($record->{$key} !== $value) $changes[$key] = ['before' => $record->{$key}, 'after' => $value];
            }
            if (!$changes) return;
            $query->update($data);
            DB::table('waste_record_history')->insert([
                'record_id' => $id,
                'actor' => $request->user()->name,
                'changes' => json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now('Europe/Istanbul')->format('Y-m-d H:i:s'),
            ]);
        });
        return redirect()->route('waste.records');
    }
    // Aynı tesisi gösterdiği teyit edilen kısa ve tam unvanlar.
    private const ADR_ENTEGRE = [
        'name' => 'ADR Entegre Atık Yönetimi',
        'vkn' => '0081356609',
        'district' => 'Tuzla',
        'pattern' => '/^\s*(?:1004249\s*-\s*)?ADR\s+ENTEGRE\s+ATIK\s+Y[ÖO]NET[İI]M[İI](?:\s+SANAY[İI]\s+T[İI]CARET\s+L[İI]M[İI]TED\s+[ŞS][İI]RKET[İI])?(?:\s*\([ÇC]KN\s*:\s*258687624\))?\s*$/iu',
    ];

    private function isAdrEntegre(string $name): bool
    {
        return preg_match(self::ADR_ENTEGRE['pattern'], $name) === 1;
    }

    private function confirmedFacility(string $role, string $name, ?string $address = null): ?array
    {
        return $this->savedFacility($role, $name, $address) ?? $this->legacyFacility($role, $name, $address);
    }

    private function statisticsFacilityKey(string $role, object $record): string
    {
        $name = trim((string) $record->{$role.'_name'});
        $addressField = $role === 'producer' ? 'address' : 'processor_address';
        $address = trim((string) ($record->{$addressField} ?? ''));
        if ($facility = $this->confirmedFacility($role, $name, $address)) {
            return 'confirmed:'.$facility['id'];
        }

        $vkn = trim((string) ($record->{$role.'_vkn'} ?? ''));
        if ($this->suspectFacilityVkn($name, $vkn)) $vkn = '';
        $normalize = fn ($value) => trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $value), 'UTF-8')));
        $district = $role === 'processor' ? $normalize($record->district ?? '') : '';

        return hash('sha256', implode('|', [$normalize($name), $vkn, $normalize($address), $district]));
    }

    private function summarizeStatisticsDays(array $days): array
    {
        krsort($days);
        $producers = [];
        $processors = [];
        $daily = [];
        $rows = 0;
        $producerDayCount = 0;

        foreach ($days as $date => $day) {
            $producers += $day['producers'];
            $processors += $day['processors'];
            $producerDayCount += count($day['producers']);
            $rows += $day['rows'];
            $daily[] = [
                'date' => $date,
                'producers' => ['size' => count($day['producers'])],
                'processors' => ['size' => count($day['processors'])],
                'rows' => $day['rows'],
            ];
        }

        return [
            'workingDays' => count($days),
            'producerCount' => count($producers),
            'processorCount' => count($processors),
            'producerDayCount' => $producerDayCount,
            'rows' => $rows,
            'daily' => $daily,
        ];
    }

    private function vehicleStatisticsData(string $today): array
    {
        $plates = ['34PFH301', '34PTR373'];
        $daysByPlate = [];
        $missingByMonth = [];
        $latestDate = null;
        $records = DB::table('waste_imports')
            ->where(fn ($query) => $query->whereNull('transport_status')->orWhere('transport_status', '!=', 'cancelled'))
            ->get(['waste_date', 'plate', 'producer_name', 'processor_name', 'producer_vkn', 'processor_vkn', 'address', 'processor_address', 'district']);

        foreach ($records as $record) {
            $date = substr((string) $record->waste_date, 0, 10);
            $month = substr($date, 0, 7);
            $plate = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $record->plate));
            if ($plate === '') {
                $missingByMonth[$month] = ($missingByMonth[$month] ?? 0) + 1;
            }
            if (!in_array($plate, $plates, true)) continue;

            $latestDate = $latestDate === null || $date > $latestDate ? $date : $latestDate;
            $daysByPlate[$plate][$date] ??= ['producers' => [], 'processors' => [], 'rows' => 0];
            $daysByPlate[$plate][$date]['rows']++;
            $daysByPlate[$plate][$date]['producers'][$this->statisticsFacilityKey('producer', $record)] = true;
            $daysByPlate[$plate][$date]['processors'][$this->statisticsFacilityKey('processor', $record)] = true;
        }

        $initialMonth = $latestDate ? substr($latestDate, 0, 7) : substr($today, 0, 7);
        $initialYear = substr($initialMonth, 0, 4);
        $monthKeys = [];
        foreach ($daysByPlate as $days) {
            foreach (array_keys($days) as $date) $monthKeys[substr($date, 0, 7)] = true;
        }
        foreach (array_keys($missingByMonth) as $month) $monthKeys[$month] = true;
        $years = array_values(array_unique(array_map(fn ($month) => substr($month, 0, 4), array_keys($monthKeys))));
        if (!$years) $years = [$initialYear];
        rsort($years);

        $months = [];
        foreach (array_keys($monthKeys) as $month) {
            $vehicles = [];
            foreach ($plates as $plate) {
                $monthDays = array_filter($daysByPlate[$plate] ?? [], fn ($day, $date) => str_starts_with($date, $month), ARRAY_FILTER_USE_BOTH);
                $vehicles[] = ['plate' => $plate, ...$this->summarizeStatisticsDays($monthDays)];
            }
            $months[$month] = ['vehicles' => $vehicles, 'missingPlates' => $missingByMonth[$month] ?? 0];
        }

        $annual = [];
        foreach ($years as $year) {
            $vehicles = [];
            $monthly = [];
            for ($index = 1; $index <= 12; $index++) {
                $month = $year.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                $monthlyVehicles = [];
                foreach ($plates as $plate) {
                    $monthDays = array_filter($daysByPlate[$plate] ?? [], fn ($day, $date) => str_starts_with($date, $month), ARRAY_FILTER_USE_BOTH);
                    $monthlyVehicles[] = ['plate' => $plate, ...$this->summarizeStatisticsDays($monthDays)];
                }
                $monthLabel = \Carbon\Carbon::createFromDate((int) $year, $index, 1)->locale('tr')->translatedFormat('F');
                $monthly[] = ['month' => $month, 'label' => $monthLabel, 'vehicles' => $monthlyVehicles];
            }

            foreach ($plates as $plate) {
                $yearDays = array_filter($daysByPlate[$plate] ?? [], fn ($day, $date) => str_starts_with($date, $year), ARRAY_FILTER_USE_BOTH);
                $summary = ['plate' => $plate, ...$this->summarizeStatisticsDays($yearDays)];
                $plateIndex = array_search($plate, $plates, true);
                $summary['monthly'] = array_map(fn ($month) => [
                    'month' => $month['month'], 'label' => $month['label'], ...$month['vehicles'][$plateIndex],
                ], $monthly);
                $vehicles[] = $summary;
            }

            $annual[$year] = [
                'vehicles' => $vehicles,
                'missingPlates' => array_sum(array_map(fn ($month) => $missingByMonth[$month] ?? 0, array_filter(array_keys($missingByMonth), fn ($month) => str_starts_with($month, $year)))),
            ];
            unset($monthly);
        }

        return ['initialMonth' => $initialMonth, 'initialYear' => $initialYear, 'years' => $years, 'months' => $months, 'annual' => $annual];
    }

    public function transferVerifiedFacilityCards(): array
    {
        $groups = [];
        foreach (DB::table('waste_imports')->orderBy('id')->cursor() as $record) {
            foreach (['producer' => 'address', 'processor' => 'processor_address'] as $role => $addressField) {
                $name = (string) $record->{$role.'_name'};
                $address = $record->{$addressField};
                $known = $this->legacyFacility($role, $name, $address);
                if (!$known || !preg_match('/^[0-9]{10}$/D', $known['vkn'] ?? '')) continue;
                $key = $role.':'.$known['id'];
                $groups[$key] ??= ['role' => $role, 'known' => $known, 'aliases' => [], 'addresses' => [], 'ckns' => []];
                $groups[$key]['aliases'][$this->facilityFingerprint($name, $address)] = true;
                if (filled($address) && ($key !== 'producer:91908' || stripos($address, 'gayrettepe') !== false)) {
                    $normalized = trim(preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(trim($address))));
                    $groups[$key]['addresses'][$normalized] = trim($address);
                }
                if (preg_match('/[ÇC]KN\s*:\s*([0-9]+)/iu', $name, $match)) {
                    $groups[$key]['ckns'][$match[1]] = $match[1];
                }
            }
        }
        return DB::transaction(function () use ($groups) {
            $created = $linked = 0;
            foreach ($groups as ['role' => $role, 'known' => $known, 'aliases' => $aliases, 'addresses' => $addresses, 'ckns' => $ckns]) {
                $existing = DB::table('waste_facility_aliases')->where('role', $role)->whereIn('fingerprint', array_keys($aliases))
                    ->distinct()->pluck('facility_id');
                if ($existing->isNotEmpty()) continue;
                $company = DB::table('waste_companies')->where('vkn', $known['vkn'])->value('id');
                $company ??= DB::table('waste_companies')->insertGetId([
                    'vkn' => $known['vkn'],
                    'name' => $known['vkn'] === '3880055204' ? 'FULYA SAĞLIK TESİSLERİ VE TİCARET A.Ş.' : preg_replace('/\s+–\s+.*$/u', '', $known['name']),
                ]);
                $existingCard = DB::table('waste_facilities')->where('role', $role)->where('name', $known['name'])->first();
                if ($existingCard && (int) $existingCard->company_id !== (int) $company) continue;
                $card = $existingCard?->id;
                if (!$card) {
                    $card = DB::table('waste_facilities')->insertGetId([
                        'company_id' => $company, 'role' => $role, 'name' => $known['name'],
                        'address' => count($addresses) === 1 ? reset($addresses) : null,
                        'district' => $known['district'], 'ckn' => count($ckns) === 1 ? reset($ckns) : null,
                    ]);
                    $created++;
                }
                foreach (array_keys($aliases) as $fingerprint) {
                    $linked += DB::table('waste_facility_aliases')->insertOrIgnore(['role' => $role, 'fingerprint' => $fingerprint, 'facility_id' => $card]);
                }
            }
            return ['created' => $created, 'aliases' => $linked];
        });
    }

    private function legacyFacility(string $role, string $name, ?string $address = null): ?array
    {
        if ($this->isAdrEntegre($name)) {
            return ['id' => '1004249', ...array_diff_key(self::ADR_ENTEGRE, ['pattern' => true])];
        }
        if ($role === 'producer' && preg_match(
            '/^\s*(?:109639\s*-\s*)?[İI]STANBUL\s+FLORENCE\s+NIGHTINGALE\s+HASTANES[İI]\s+ANON[İI]M\s+[ŞS][İI]RKET[İI](?:\s*\([ÇC]KN\s*:\s*227352966\))?\s*$/iu',
            $name,
        )) {
            return ['id' => '109639', 'name' => 'İSTANBUL FLORENCE NIGHTINGALE HASTANESİ ANONİM ŞİRKETİ',
                'vkn' => '4810568755', 'district' => null];
        }
        if ($role === 'producer') {
            $normalizeTurkish = fn ($value) => mb_strtolower(strtr($value, ['İ' => 'i', 'I' => 'ı']));
            $normalized = $normalizeTurkish(trim($name));
            $location = $normalizeTurkish((string) $address);
            foreach ([
                ['89282', 'AKDAĞLAR MADENCİLİK SANAYİ VE TİC. A.Ş. – AYAZAĞA', '0200040933',
                    str_contains($normalized, 'akdağlar') && str_contains($normalized, '226120744') && str_contains($location, 'ayazağa')],
                ['233618946', 'FULYA SAĞLIK TESİSLERİ VE TİCARET A.Ş. – ATAŞEHİR ŞUBESİ', '3880055204',
                    str_contains($normalized, 'fulya') && str_contains($normalized, 'ataşehir') && str_contains($location, 'şenlik')],
                ['91908', 'FULYA SAĞLIK TESİSLERİ VE TİCARET A.Ş. – GAYRETTEPE ŞUBESİ', '3880055204',
                    str_contains($normalized, 'fulya') && (
                        preg_match('/\bçkn\s*:\s*226209944\b/u', $normalized)
                        || (str_contains($location, 'gayrettepe') && str_contains($location, 'güder'))
                    )],
                ['99736', 'FULYA SAĞLIK TESİSLERİ VE TİCARET A.Ş. – KADIKÖY ŞUBESİ', '3880055204',
                    str_contains($normalized, 'fulya') && str_contains($normalized, 'kadıköy')
                        && (str_starts_with($normalized, '99736') || str_contains($location, 'feneryolu'))],
                ['1259762', 'ECOSTEP ENERJİ SANAYİ TİCARET LİMİTED ŞİRKETİ', '3241166768',
                    $normalized === 'ecostep' || (str_contains($normalized, 'ecostep') && str_starts_with($normalized, '1259762'))],
            ] as [$id, $canonical, $vkn, $matches]) {
                if ($matches || $normalized === $normalizeTurkish($canonical)) {
                    return ['id' => $id, 'name' => $canonical, 'vkn' => $vkn, 'district' => null];
                }
            }
        }
        if ($role !== 'processor') return null;

        foreach ([
            ['id' => '1259762', 'name' => 'Ecostep Enerji', 'vkn' => '3241166768', 'district' => null, 'alias' => 'Ecostep'],
            ['id' => '1193429', 'name' => 'Energrom Enerji – Şile Şubesi', 'vkn' => '3350449399', 'district' => 'Şile', 'alias' => 'Energrom'],
            ['id' => '1205273', 'name' => 'Kuzey İstanbul – Kömürcüoda Şubesi', 'vkn' => '6010946141', 'district' => 'Şile', 'alias' => 'Kuzey İstanbul'],
            ['id' => '1211399', 'name' => 'Eroğlu Çevre Endüstriyel Atık Yönetimi', 'vkn' => '3660553605', 'district' => null, 'alias' => 'Eroğlu'],
            ['id' => '10514', 'name' => 'İZAYDAŞ', 'vkn' => '4840003120', 'district' => 'İzmit', 'alias' => 'İZAYDAŞ'],
            ['id' => '1049678', 'name' => 'BMC ATIK YÖNETİMİ ANONİM ŞİRKETİ', 'vkn' => '1781460753', 'district' => 'Hendek', 'alias' => 'BMC Atık Yönetimi'],
        ] as $facility) {
            $value = trim($name);
            if (preg_match('/^'.$facility['id'].'\s*-\s*/u', $value)
                || strcasecmp($value, $facility['name']) === 0
                || mb_strtolower(str_replace('İ', 'i', $value)) === mb_strtolower(str_replace('İ', 'i', $facility['alias']))) {
                return array_diff_key($facility, ['alias' => true]);
            }
        }
        return null;
    }

    private function suspectFacilityVkn(string $name, ?string $vkn): bool
    {
        return stripos($name, 'fulya') !== false
            && in_array(trim((string) $vkn), ['3880055205', '3880055206', '3880784742'], true);
    }

    private function wasteCatalog(): array
    {
        return json_decode(file_get_contents(resource_path('waste-codes-ek4.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $admin = $user->role === 'admin';
        $wasteRole = $admin ? null : $user->moduleAccesses()
            ->whereHas('module', fn ($query) => $query->where('key', 'waste'))
            ->value('role');
        $statisticsOnly = !$admin && $request->routeIs('waste.statistics') && $wasteRole === AppModule::ROLE_VIEWER;

        if ($statisticsOnly) {
            $today = now('Europe/Istanbul')->toDateString();

            return Inertia::render('AtikTasimaKayitlari', [
                'records' => [],
                'history' => [],
                'admin' => false,
                'canWrite' => false,
                'today' => $today,
                'wasteCatalog' => ['codes' => []],
                'adrEntegre' => [],
                'canClassifyAdr' => false,
                'adrCatalog' => [],
                'adrUsers' => [],
                'facilityView' => null,
                'statisticsView' => true,
                'statisticsOnly' => true,
                'vehicleStatisticsData' => $this->vehicleStatisticsData($today),
            ]);
        }

        $visibleRecords = DB::table('waste_imports')->when(!$admin, fn ($query) => $query
            ->where(fn ($status) => $status->whereNull('transport_status')->orWhere('transport_status', '!=', 'cancelled')));
        return Inertia::render('AtikTasimaKayitlari', [
            'facilityView' => $request->route('facilityView'),
            'statisticsView' => (bool) $request->route('statisticsView'),
            'canClassifyAdr' => (bool) $user->can_classify_waste_adr,
            'adrCatalog' => self::ADR_CATALOG,
            'adrUsers' => $user->role === 'admin' ? DB::table('users')->where('active', true)
                ->orderBy('name')->get(['id', 'name', 'can_classify_waste_adr']) : [],
            'wasteCatalog' => $this->wasteCatalog(),
            'records' => (clone $visibleRecords)->orderByDesc('waste_date')->orderByDesc('id')->get()->map(function ($record) {
                foreach (['producer', 'processor'] as $role) {
                    $addressField = $role === 'producer' ? 'address' : 'processor_address';
                    $record->{$role.'_vkn_suspect'} = $this->suspectFacilityVkn(
                        (string) $record->{$role.'_name'}, $record->{$role.'_vkn'},
                    );
                    if ($record->{$role.'_vkn_suspect'}) {
                        $record->{$role.'_vkn_original'} = $record->{$role.'_vkn'};
                    }
                    if ($facility = $this->confirmedFacility($role, (string) $record->{$role.'_name'}, $record->{$addressField})) {
                        $record->{$role.'_confirmed_facility'} = $facility;
                        if ($facility['vkn'] !== null) $record->{$role.'_vkn'} = $facility['vkn'];
                        if (blank($record->{$addressField})) {
                            $record->{$addressField} = $this->facilityAddress($role, $facility, $record->district);
                        }
                    }
                }
                return $record;
            }),
            'history' => DB::table('waste_record_history')->whereIn('record_id', (clone $visibleRecords)->select('id'))->latest('id')->get()->groupBy('record_id'),
            'admin' => $user->role === 'admin',
            'canWrite' => $user->role === 'admin' || $user->moduleAccesses()
                ->where('role', 'advisor')->whereHas('module', fn ($q) => $q->where('key', 'waste'))->exists(),
            'today' => now('Europe/Istanbul')->toDateString(),
        ]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $admin = $request->user()->role === 'admin';
        $today = now('Europe/Istanbul')->toDateString();
        $request->merge(['waste_code' => preg_replace('/[\s*]+$/u', '', preg_replace('/\s+/u', '', (string) $request->input('waste_code')))]);
        $fields = $request->validate([
            'new_facility' => 'sometimes|array:producer,processor',
            'new_facility.*' => 'boolean',
            'facility_record' => 'sometimes|array:producer,processor',
            'facility_record.*' => 'integer|exists:waste_imports,id',
            'waste_date' => ['required', 'date_format:Y-m-d', ...($admin ? [] : ['after_or_equal:'.$today])],
            'waste_code' => ['required', 'regex:/^[0-9]{6}$/'],
            'tkn' => 'nullable|string|max:32',
            'tdn' => 'nullable|string|max:32',
            'confirm_missing_motat' => 'sometimes|boolean',
            'producer_name' => 'required|string|max:500',
            'processor_name' => 'required|string|max:500',
            'district' => 'nullable|string|max:200',
            'address' => 'nullable|string|max:1000',
            'processor_address' => 'nullable|string|max:1000',
            'producer_vkn' => 'nullable|string|max:20',
            'processor_vkn' => 'nullable|string|max:20',
            'observed_amount' => 'nullable|string|max:200',
            'period' => 'nullable|string|max:100',
            'plate' => $admin ? 'nullable|string|max:100' : 'prohibited',
        ]);
        $newFacilities = $fields['new_facility'] ?? [];
        $facilityRecords = $fields['facility_record'] ?? [];
        unset($fields['new_facility'], $fields['facility_record']);
        if ((!filled($fields['tkn'] ?? null) || !filled($fields['tdn'] ?? null)) && !$request->boolean('confirm_missing_motat')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'confirm_missing_motat' => 'TKN veya TDN eksik. Kaydetmek için eksik numaralarla devam etmeyi onaylayın.',
            ]);
        }
        unset($fields['confirm_missing_motat']);
        foreach (['producer', 'processor'] as $role) {
            $addressField = $role === 'producer' ? 'address' : 'processor_address';
            if ($facility = $this->confirmedFacility($role, $fields[$role.'_name'], $fields[$addressField] ?? null)) {
                $fields[$role.'_name'] = $facility['name'];
                if ($facility['vkn'] !== null) $fields[$role.'_vkn'] = $facility['vkn'];
                if (blank($fields[$addressField] ?? null)) {
                    $fields[$addressField] = $this->facilityAddress($role, $facility, $fields['district'] ?? null);
                }
            }
        }
        $entry = $this->wasteCatalog()['codes'][$fields['waste_code']] ?? null;
        if (!$entry) {
            throw \Illuminate\Validation\ValidationException::withMessages(['waste_code' => 'Bu atık kodu Ek-4 listesinde bulunamadı.']);
        }
        $fields['waste_name'] = $entry['description'];
        if (!$admin) unset($fields['plate']);

        DB::transaction(function () use ($id, $fields, $request, $admin, $today, $newFacilities, $facilityRecords) {
            $records = DB::table('waste_imports');
            $previous = $id === null ? null : (clone $records)->where('id', $id)->lockForUpdate()->first();
            abort_if($id !== null && !$previous, 404);
            abort_if($previous && !$admin && substr($previous->waste_date, 0, 10) < $today, 403);
            abort_if($previous?->transport_status === 'cancelled', 409, 'Gerçekleşmeyen taşıma düzenlenemez.');
            $data = $fields;
            foreach (['producer' => 'address', 'processor' => 'processor_address'] as $role => $addressField) {
                if (!($newFacilities[$role] ?? false)) {
                    $source = isset($facilityRecords[$role]) ? (clone $records)->find($facilityRecords[$role]) : null;
                    $this->completeRegisteredFacility($role, $addressField, $data, $source);
                    continue;
                }
                $nameField = $role.'_name';
                $vknField = $role.'_vkn';
                if (!preg_match('/^[0-9]{10}$/', $data[$vknField] ?? '') || !filled($data[$addressField] ?? null)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $nameField => 'Yeni tesis için 10 haneli VKN ve açık adres girin.',
                    ]);
                }
                $normalize = fn ($value) => trim(preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], (string) $value))));
                $existing = (clone $records)->where($vknField, $data[$vknField])->get([$nameField, $addressField]);
                foreach ($existing as $facility) {
                    if ($normalize($facility->{$addressField}) === $normalize($data[$addressField])) {
                        $data[$nameField] = $facility->{$nameField};
                        $data[$addressField] = $facility->{$addressField};
                        break;
                    }
                }
            }
            if (!$previous || $previous->waste_code !== $data['waste_code']) {
                $data += $this->adrForWasteCode($data['waste_code'], $id);
                $data += ['adr_approved_at' => null, 'adr_approved_by' => null, 'adr_reference' => null];
            }
            $changes = [];
            foreach ($data as $field => $value) {
                $old = $previous?->{$field} ?? null;
                if ($field === 'waste_date' && $old) $old = substr($old, 0, 10);
                if ($old !== $value) $changes[$field] = ['before' => $old, 'after' => $value];
            }
            if (!$changes) return;
            if ($previous) {
                (clone $records)->where('id', $id)->update($data);
            } else {
                $id = $records->insertGetId($data);
            }
            DB::table('waste_record_history')->insert([
                'record_id' => $id,
                'actor' => $request->user()->name,
                'changes' => json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now('Europe/Istanbul')->format('Y-m-d H:i:s'),
            ]);
        });
        return redirect()->route('waste.records');
    }

    private function completeRegisteredFacility(string $role, string $addressField, array &$data, ?object $source): void
    {
        $nameField = $role.'_name';
        $vknField = $role.'_vkn';
        $name = $data[$nameField];
        $sourceFacility = null;
        $facility = $this->savedFacility($role, $name, $data[$addressField] ?? null);
        if ($source) {
            $sourceName = (string) $source->$nameField;
            $sourceFacility = $this->confirmedFacility($role, $sourceName, $source->$addressField);
            $matches = $this->facilityFingerprint($sourceName, null) === $this->facilityFingerprint($name, null)
                || ($sourceFacility && $sourceFacility['name'] === $name);
            if (!$matches) {
                throw \Illuminate\Validation\ValidationException::withMessages([$nameField => 'Tesis önerisini tekrar seçin.']);
            }
            if (!empty($sourceFacility['card_id'])) $facility = $sourceFacility;
        }
        $facility ??= $this->savedFacility($role, $name, null);
        if (!$facility && !$source) return;

        $cards = DB::table('waste_facilities')->where('role', $role);
        $card = $facility ? (clone $cards)->where('id', $facility['card_id'])->lockForUpdate()->first()
            : (clone $cards)->where('name', $name)->lockForUpdate()->first();
        $company = $card ? DB::table('waste_companies')->find($card->company_id) : null;
        $knownVkn = $company->vkn ?? $sourceFacility['vkn'] ?? $source?->$vknField;
        $validKnownVkn = preg_match('/^[0-9]{10}$/D', (string) $knownVkn)
            && !$this->suspectFacilityVkn($name, $knownVkn);
        $vkn = $validKnownVkn ? $knownVkn : ($data[$vknField] ?? null);
        $knownAddress = $card ? $card->address : $source?->$addressField;
        $errors = [];
        if (!preg_match('/^[0-9]{10}$/D', (string) $vkn) || $this->suspectFacilityVkn($name, $vkn)) {
            $errors[$vknField] = 'Kayıtlı tesisin 10 haneli VKN’sini girin.';
        }
        if (blank($data[$addressField] ?? $knownAddress) || blank($data[$addressField] ?? null) && $card
            && !$this->facilityAddress($role, $facility ?? (array) $card, $data['district'] ?? null)) {
            $errors[$addressField] = 'Kayıtlı tesisin açık adresini girin.';
        }
        if ($errors) throw \Illuminate\Validation\ValidationException::withMessages($errors);

        $data[$vknField] = $vkn;
        $data[$addressField] ??= $knownAddress;
        if (!$card && filled($knownAddress) && $validKnownVkn) return;

        $companyId = $validKnownVkn && $company ? $company->id : null;
        if (!$companyId) {
            $companies = DB::table('waste_companies');
            $companies->insertOrIgnore(['vkn' => $data[$vknField], 'name' => $company->name ?? $name]);
            $companyId = $companies->where('vkn', $data[$vknField])->sole()->id;
        }
        if ($card) {
            (clone $cards)->where('id', $card->id)->update([
                'company_id' => $companyId, 'address' => filled($knownAddress) ? $knownAddress : $data[$addressField],
            ]);
            return;
        }

        preg_match('/[ÇC]KN\s*:\s*([0-9]{1,20})\b/iu', $sourceName, $ckn);
        $cardId = DB::table('waste_facilities')->insertGetId([
            'role' => $role, 'company_id' => $companyId, 'name' => $name,
            'address' => $data[$addressField], 'district' => $role === 'processor' ? ($data['district'] ?? null) : null,
            'ckn' => $ckn[1] ?? null,
        ]);
        DB::table('waste_facility_aliases')->insert([
            'role' => $role, 'facility_id' => $cardId,
            'fingerprint' => $this->facilityFingerprint($sourceName, $source->$addressField),
        ]);
    }

    public function cancel(Request $request, int $id)
    {
        DB::transaction(function () use ($request, $id) {
            $query = DB::table('waste_imports')->where('id', $id);
            $record = (clone $query)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if($request->user()->role !== 'admin' && substr($record->waste_date, 0, 10) < now('Europe/Istanbul')->toDateString(), 403);
            if ($record->transport_status === 'cancelled') return;
            $query->update(['transport_status' => 'cancelled']);
            DB::table('waste_record_history')->insert([
                'record_id' => $id, 'actor' => $request->user()->name,
                'changes' => json_encode(['transport_status' => ['before' => $record->transport_status, 'after' => 'Gerçekleşmedi']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => now('Europe/Istanbul')->format('Y-m-d H:i:s'),
            ]);
        });
        return redirect()->route('waste.records');
    }
}
