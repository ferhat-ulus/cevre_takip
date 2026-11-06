<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog; // YENİ EKLENDİ
use App\Models\Firm;
use App\Models\FirmLog;
use App\Services\ComplianceEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz yok.');
        }

        $users = User::orderBy('role', 'desc')->orderBy('name')->get();
        $firmCounts = Firm::query()->get(['data'])
            ->reject(fn (Firm $firm) => ComplianceEngine::isEngagementClosed($firm->data))
            ->countBy(fn (Firm $firm) => $firm->data['consultant'] ?? '');
        $handoverHistory = ActivityLog::with('user:id,name')
            ->whereIn('action', ['consultant_handover', 'user_status_changed'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => 'activity-' . $log->id,
                'description' => $log->description,
                'actor' => $log->user?->name ?? 'Sistem',
                'created_at' => $log->created_at,
            ]);
        $firmLogs = FirmLog::with('user:id,name')
            ->where('action', 'consultant_changed')
                ->where(function ($query) {
                    $query->whereNull('reason')
                        ->orWhere('reason', 'not like', 'CSV_IMPORT:%');
                })
                ->latest()
                ->limit(50)
                ->get();
        $firmNames = Firm::query()
            ->whereIn('id', $firmLogs->pluck('firm_id')->unique())
            ->get(['id', 'data'])
            ->mapWithKeys(fn (Firm $firm) => [$firm->id => $firm->data['name'] ?? 'İsimsiz tesis']);
        $firmHistory = $firmLogs->map(fn (FirmLog $log) => [
            'id' => 'firm-' . $log->id,
            'description' => ($firmNames[$log->firm_id] ?? 'Silinmiş tesis') . ': ' . $log->description,
            'actor' => $log->user?->name ?? 'Sistem',
            'created_at' => $log->created_at,
        ]);
        $consultantHistory = $handoverHistory
            ->concat($firmHistory)
            ->sortByDesc('created_at')
            ->take(25)
            ->values();
        
        return Inertia::render('Kullanicilar', [
            'users' => $users,
            'firmCounts' => $firmCounts,
            'consultantFirms' => Firm::query()->orderBy('id')->get(['id', 'data'])
                ->reject(fn (Firm $firm) => ComplianceEngine::isEngagementClosed($firm->data))
                ->map(fn (Firm $firm) => [
                'id' => $firm->id,
                'name' => $firm->data['name'] ?? 'İsimsiz tesis',
                'consultant' => $firm->data['consultant'] ?? '',
            ])->values(),
            'consultantHistory' => $consultantHistory,
            'isAdmin' => true
        ]);
    }

    public function consultantOverview(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);

        $consultants = User::query()
            ->where('role', 'employee')
            ->orderBy('name')
            ->get(['id', 'name', 'active']);

        $firms = Firm::query()->orderBy('id')->get(['id', 'data'])->map(function (Firm $firm) {
            return [
                'id' => $firm->id,
                'name' => $firm->data['name'] ?? 'İsimsiz tesis',
                'consultant' => $firm->data['consultant'] ?? 'Atanmamış',
                'status' => $firm->data['status'] ?? 'aktif',
            ];
        });

        $logs = FirmLog::query()
            ->with('user:id,name')
            ->where('action', 'consultant_changed')
            ->orderBy('id')
            ->get();

        $historyByFirm = [];
        $history = [];
        $tenuresByConsultant = [];
        $openTenures = [];
        $consultantNamesByKey = [];
        $firmNames = $firms->keyBy('id')->map(fn (array $firm) => $firm['name']);
        $consultantKey = function (?string $name): string {
            $name = mb_strtoupper(trim((string) $name), 'UTF-8');
            $name = str_replace('İ', 'I', $name);
            $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? '';
            $name = preg_replace('/\s+/u', ' ', $name) ?? '';

            return trim($name);
        };
        $isKnownConsultant = fn (?string $name) => !in_array($consultantKey($name), ['', 'BILINMIYOR', 'ATANMAMIS'], true);
        $aliasesByKey = [];
        $rememberAlias = function (?string $name) use (&$aliasesByKey, $consultantKey, $isKnownConsultant): void {
            if (!$isKnownConsultant($name)) {
                return;
            }

            $display = trim((string) $name);
            $aliasesByKey[$consultantKey($display)][$display] = $display;
        };

        foreach ($logs as $log) {
            $from = $log->from_consultant;
            $to = $log->to_consultant;
            if (($from === null || $to === null)
                && preg_match("/(?:değiştirildi|devri):\\s*'([^']*)'\\s+yerine\\s+'([^']*)'\\s+atandı\\./u", $log->description, $matches)) {
                $from ??= $matches[1];
                $to ??= $matches[2];
            }

            $reason = $log->reason;
            if ($reason === null && preg_match('/Gerekçe:\\s*(.*)$/u', $log->description, $matches)) {
                $reason = $matches[1];
            }

            $firmId = (string) $log->firm_id;
            $firmName = $firmNames[$firmId] ?? 'Silinmiş tesis';
            $rememberAlias($from);
            $rememberAlias($to);
            $fromKey = $consultantKey($from);
            $toKey = $consultantKey($to);
            $fromIsKnown = $isKnownConsultant($from);
            $toIsKnown = $isKnownConsultant($to);
            $isSameConsultant = $fromKey === $toKey;
            $eventDate = $log->history_date
                ?? (str_starts_with((string) $reason, 'CSV_IMPORT:')
                    ? null
                    : $log->created_at?->toIso8601String());

            $incompleteEnd = $fromIsKnown && !$toIsKnown && $eventDate === null;
            if ($fromIsKnown && !$isSameConsultant && !$incompleteEnd) {
                if (isset($openTenures[$firmId][$fromKey])) {
                    [$openConsultantKey, $tenureIndex] = $openTenures[$firmId][$fromKey];
                    $tenuresByConsultant[$openConsultantKey][$tenureIndex]['left_at'] = $eventDate;
                    $tenuresByConsultant[$openConsultantKey][$tenureIndex]['handed_to'] = $toIsKnown ? $to : null;
                    $tenuresByConsultant[$openConsultantKey][$tenureIndex]['is_current'] = false;
                    unset($openTenures[$firmId][$fromKey]);
                } else {
                    $consultantNamesByKey[$fromKey] ??= $from;
                    $tenuresByConsultant[$fromKey][] = [
                        'firm_id' => $firmId,
                        'firm_name' => $firmName,
                        'received_at' => null,
                        'left_at' => $eventDate,
                        'received_from' => null,
                        'handed_to' => $toIsKnown ? $to : null,
                        'is_current' => false,
                    ];
                }
            }

            if ($toIsKnown && !$isSameConsultant) {
                $consultantNamesByKey[$toKey] ??= $to;
                $tenuresByConsultant[$toKey][] = [
                    'firm_id' => $firmId,
                    'firm_name' => $firmName,
                    'received_at' => $eventDate,
                    'left_at' => null,
                    'received_from' => $fromIsKnown ? $from : null,
                    'handed_to' => null,
                    'is_current' => false,
                ];
                $openTenures[$firmId][$toKey] = [$toKey, array_key_last($tenuresByConsultant[$toKey])];
            }

            $entry = [
                'id' => $log->id,
                'firm_id' => $firmId,
                'from' => $from ?? 'Bilinmiyor',
                'to' => $to ?? 'Bilinmiyor',
                'reason' => $reason,
                'actor' => $log->user?->name ?? 'Sistem',
                'created_at' => $log->created_at,
                'history_date' => $log->history_date,
            ];
            $historyByFirm[$log->firm_id][] = $entry;
            $history[] = $entry;
        }

        foreach ($firms as $firm) {
            $currentConsultant = (string) $firm['consultant'];
            $rememberAlias($currentConsultant);
            $currentKey = $consultantKey($currentConsultant);
            if (!$isKnownConsultant($currentConsultant)) {
                continue;
            }

            if (isset($openTenures[$firm['id']][$currentKey])) {
                [$openConsultantKey, $tenureIndex] = $openTenures[$firm['id']][$currentKey];
                $tenuresByConsultant[$openConsultantKey][$tenureIndex]['is_current'] = true;
                continue;
            }

            $consultantNamesByKey[$currentKey] ??= $currentConsultant;
            $tenuresByConsultant[$currentKey][] = [
                'firm_id' => (string) $firm['id'],
                'firm_name' => $firm['name'],
                'received_at' => null,
                'left_at' => null,
                'received_from' => null,
                'handed_to' => null,
                'is_current' => true,
            ];
        }

        $portfolioFields = function (array $assignments): array {
            $history = collect($assignments)->sortByDesc('received_at')->values();

            return [
                'assignmentHistory' => $history,
                'periodCount' => $history->count(),
                'pastFirmCount' => $history->where('is_current', false)->pluck('firm_id')->unique()->count(),
            ];
        };
        $aliasesFor = function (string $name) use ($aliasesByKey, $consultantKey): array {
            $key = $consultantKey($name);
            $official = mb_strtoupper(trim($name), 'UTF-8');

            return collect($aliasesByKey[$key] ?? [])
                ->reject(fn (string $alias) => mb_strtoupper($alias, 'UTF-8') === $official)
                ->sort()
                ->values()
                ->all();
        };

        $consultantRows = $consultants->map(function (User $consultant) use ($firms, $historyByFirm, $tenuresByConsultant, $consultantKey, $portfolioFields, $aliasesFor) {
            $key = $consultantKey($consultant->name);
            $assignedFirms = $firms
                ->filter(fn (array $firm) => $consultantKey($firm['consultant']) === $key)
                ->map(function (array $firm) use ($historyByFirm) {
                    $firm['history'] = $historyByFirm[$firm['id']] ?? [];
                    $firm['previousConsultant'] = $firm['history'][0]['from'] ?? null;

                    return $firm;
                })
                ->values();

            return [
                'id' => $consultant->id,
                'name' => $consultant->name,
                'active' => $consultant->active,
                'historical' => false,
                'firmCount' => $assignedFirms->count(),
                'firms' => $assignedFirms,
                'aliases' => $aliasesFor($consultant->name),
                ...$portfolioFields($tenuresByConsultant[$key] ?? []),
            ];
        });

        $knownConsultantKeys = $consultants
            ->map(fn (User $consultant) => $consultantKey($consultant->name))
            ->all();
        $historicalRows = [];
        foreach ($tenuresByConsultant as $nameKey => $assignments) {
            if (in_array($nameKey, $knownConsultantKeys, true)) {
                continue;
            }

            $name = $consultantNamesByKey[$nameKey];
            $historicalRows[] = [
                'id' => 'history-' . $nameKey,
                'name' => $name,
                'active' => false,
                'historical' => true,
                'firmCount' => 0,
                'firms' => [],
                'aliases' => $aliasesFor($name),
                ...$portfolioFields($assignments),
            ];
        }
        $historicalRows = collect($historicalRows)
            ->sortBy(fn (array $row) => $consultantKey($row['name']))
            ->values();
        $consultantRows = $consultantRows->concat($historicalRows);

        $unassignedFirms = $firms
            ->filter(fn (array $firm) => in_array(mb_strtoupper(trim((string) $firm['consultant']), 'UTF-8'), ['', 'ATANMAMIŞ'], true))
            ->values();
        $history = collect($history)
            ->map(function (array $entry) use ($firmNames) {
                $entry['firm_name'] = $firmNames[$entry['firm_id']] ?? 'Silinmiş tesis';

                return $entry;
            })
            ->sortByDesc(function (array $entry) {
                if ($entry['history_date']) {
                    return strlen($entry['history_date']) === 7
                        ? $entry['history_date'] . '-00'
                        : $entry['history_date'];
                }

                return str_starts_with((string) $entry['reason'], 'CSV_IMPORT:')
                    ? ''
                    : $entry['created_at'];
            })
            ->values();
        $historyByFirm = collect($historyByFirm)
            ->map(fn (array $entries) => collect($entries)
                ->sortByDesc(fn (array $entry) => $entry['history_date']
                    ? (strlen($entry['history_date']) === 7 ? $entry['history_date'] . '-00' : $entry['history_date'])
                    : (str_starts_with((string) $entry['reason'], 'CSV_IMPORT:') ? '' : $entry['created_at']))
                ->values());

        $consultantRows = $consultantRows->map(function (array $consultant) use ($historyByFirm) {
            $consultant['firms'] = collect($consultant['firms'])
                ->map(function (array $firm) use ($historyByFirm) {
                    $firm['history'] = $historyByFirm[$firm['id']] ?? collect();
                    $firm['previousConsultant'] = $firm['history'][0]['from'] ?? null;

                    return $firm;
                })
                ->values();

            return $consultant;
        })->values();

        return Inertia::render('DanismanTakibi', [
            'consultants' => $consultantRows,
            'unassignedFirms' => $unassignedFirms,
            'history' => $history,
            'summary' => [
                'consultants' => $consultants->count(),
                'activeConsultants' => $consultants->where('active', true)->count(),
                'inactiveConsultants' => $consultants->where('active', false)->count(),
                'firms' => $firms->count(),
                'unassignedFirms' => $unassignedFirms->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username|alpha_dash',
            'email' => 'nullable|email|max:255',
            'role' => 'required|in:admin,employee',
            'password' => 'nullable|string|min:6'
        ]);

        $tempPassword = $request->password ?: Str::random(10);

        $user = User::forceCreate([
            'name' => mb_strtoupper($request->name, 'UTF-8'),
            'username' => strtolower($request->username),
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($tempPassword),
            'active' => 1,
            'must_change' => $request->password ? 0 : 1,
            'sess_ver' => 1,
            'cal_token' => Str::random(32)
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user_created',
            'description' => "Yeni kullanıcı eklendi: {$user->name} ({$user->role})",
            'ip_address' => $request->ip()
        ]);

        return response()->json(['ok' => true, 'password' => $tempPassword, 'user' => $user]);
    }

    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $user = User::findOrFail($id);
        $patch = $request->all();
        $response = ['ok' => true];
        $logDetails = [];
        $hasAssignedFirms = $user->role === 'employee'
            && Firm::query()->where('data->consultant', $user->name)->get()
                ->contains(fn (Firm $firm) => !ComplianceEngine::isEngagementClosed($firm->data));

        if ($hasAssignedFirms
            && (($user->active && array_key_exists('active', $patch) && !(bool) $patch['active'])
                || (isset($patch['role']) && $patch['role'] === 'admin'))) {
            return response()->json([
                'error' => 'Bu danışmana atanmış tesisler var. Önce tesisleri devir işlemiyle başka bir danışmana aktarın.',
            ], 422);
        }

        if (isset($patch['email']) && $patch['email'] !== $user->email) {
            $user->email = $patch['email'];
            $logDetails[] = "E-posta güncellendi";
        }

        if (isset($patch['role']) && in_array($patch['role'], ['admin', 'employee']) && $user->role !== $patch['role']) {
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->where('active', true)->count();
                if ($adminCount <= 1) {
                    return response()->json(['error' => 'Son yöneticinin rolü değiştirilemez.'], 422);
                }
            }
            $logDetails[] = "Rol değiştirildi: {$user->role} -> {$patch['role']}";
            $user->role = $patch['role'];
            $user->sess_ver += 1; 
        }

        // DURUM DEĞİŞİKLİĞİ VE LOGLAMA
        if (isset($patch['active']) && (bool)$patch['active'] !== (bool)$user->active) {
            if ($user->role === 'admin' && $user->active) {
                $adminCount = User::where('role', 'admin')->where('active', true)->count();
                if ($adminCount <= 1) {
                    return response()->json(['error' => 'Son yönetici pasif yapılamaz.'], 422);
                }
            }
            $user->active = (bool)$patch['active'];
            $user->sess_ver += 1;
            
            $statusText = $user->active ? 'Aktif' : 'Pasif (İşten Ayrıldı / Arşiv)';
            $logDetails[] = "Durum değiştirildi: {$statusText}";
            
            // SİSTEM LOGLARINA KAYIT
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'user_status_changed',
                'description' => "{$user->name} adlı kullanıcının durumu '{$statusText}' olarak değiştirildi.",
                'ip_address' => $request->ip()
            ]);
        }

        if (isset($patch['resetPassword']) && $patch['resetPassword'] === true) {
            $newPassword = !empty($patch['new_password']) ? $patch['new_password'] : Str::random(10);
            $user->password = Hash::make($newPassword);
            $user->must_change = empty($patch['new_password']) ? 1 : 0;
            $user->sess_ver += 1;
            $response['password'] = $newPassword;
            $logDetails[] = "Şifre sıfırlandı";
            
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'user_password_reset',
                'description' => "{$user->name} adlı kullanıcının şifresi sıfırlandı.",
                'ip_address' => $request->ip()
            ]);
        }

        $user->save();

        if (count($logDetails) > 0 && !isset($patch['resetPassword'])) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'user_updated',
                'description' => "{$user->name} kullanıcısında değişiklik yapıldı: " . implode(', ', $logDetails),
                'ip_address' => $request->ip()
            ]);
        }

        return response()->json($response);
    }

    public function handoverConsultant(Request $request, $id)
    {
        $request->validate([
            'replacement_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
            'deactivate' => ['sometimes', 'boolean'],
            'firm_ids' => ['sometimes', 'array', 'min:1'],
            'firm_ids.*' => ['string'],
        ]);

        $consultant = User::findOrFail($id);
        if ($consultant->role !== 'employee') {
            return response()->json(['error' => 'Tesis devri yalnızca danışman hesaplarında yapılabilir.'], 422);
        }
        if ($consultant->id === $request->user()->id && ($request->boolean('deactivate') || $request->filled('replacement_user_id'))) {
            return response()->json(['error' => 'Kendi danışman hesabınızın görevini devredemezsiniz.'], 422);
        }

        $replacement = null;
        if ($request->filled('replacement_user_id')) {
            $replacement = User::findOrFail($request->integer('replacement_user_id'));
            if ($replacement->role !== 'employee' || !$replacement->active || $replacement->id === $consultant->id) {
                return response()->json(['error' => 'Devir yapılacak kişi aktif bir danışman olmalıdır.'], 422);
            }
        }
        $oldName = $consultant->name;
        $newName = $replacement?->name ?? 'Atanmamış';
        $reason = trim($request->string('reason')->toString());
        $deactivate = $request->boolean('deactivate');
        $firmQuery = Firm::query()->where('data->consultant', $oldName)->get()
            ->reject(fn (Firm $firm) => ComplianceEngine::isEngagementClosed($firm->data));
        if ($request->exists('firm_ids')) {
            $firmIds = array_values(array_unique($request->input('firm_ids', [])));
            $firms = $firmQuery->whereIn('id', $firmIds)->values();
            if ($firms->count() !== count($firmIds)) {
                return response()->json(['error' => 'Seçilen tesislerden bazıları bu danışmana ait değil.'], 422);
            }
        } else {
            $firms = $firmQuery->values();
        }
        if ($firms->isEmpty()) {
            return response()->json(['error' => 'Devredilecek tesis seçin.'], 422);
        }
        $remainingFirms = Firm::query()
            ->where('data->consultant', $oldName)
            ->get()
            ->contains(fn (Firm $firm) => !ComplianceEngine::isEngagementClosed($firm->data) && !$firms->pluck('id')->contains($firm->id));
        if ($deactivate && $remainingFirms) {
            return response()->json(['error' => 'Danışmanı pasife almak için bütün tesislerin devredilmesi gerekir.'], 422);
        }
        $transferredCount = 0;

        DB::transaction(function () use ($request, $consultant, $firms, $oldName, $newName, $reason, $deactivate, &$transferredCount) {
            foreach ($firms as $firm) {
                $data = $firm->data;
                $data['consultant'] = $newName;
                $data['updatedAt'] = now()->toDateTimeString();
                $data['updatedBy'] = $request->user()->name;
                $firm->data = $data;
                $firm->save();

                FirmLog::create([
                    'firm_id' => $firm->id,
                    'user_id' => $request->user()->id,
                    'action' => 'consultant_changed',
                    'description' => "Danışman devri: '{$oldName}' yerine '{$newName}' atandı. Gerekçe: {$reason}",
                    'from_consultant' => $oldName,
                    'to_consultant' => $newName,
                    'reason' => $reason,
                ]);
                $transferredCount++;
            }

            if ($deactivate && $consultant->active) {
                $consultant->active = false;
                $consultant->sess_ver += 1;
                $consultant->save();
            }

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'consultant_handover',
                'description' => "{$oldName} danışmanının {$transferredCount} tesisi {$newName} danışmanına devredildi. Gerekçe: {$reason}"
                    . ($deactivate ? ' Danışman hesabı pasife alındı.' : ''),
                'ip_address' => $request->ip(),
            ]);
        });

        return response()->json(['ok' => true, 'transferred' => $transferredCount]);
    }
}