<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Firm;
use App\Models\Config;
use App\Models\User;
use App\Models\Visit;
use App\Models\FirmLog;
use App\Services\ComplianceEngine;
use Carbon\Carbon;
use Inertia\Inertia;

class FirmController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Firm::query()->with('consultant:id,name,username')->visibleTo($user);
        
        $firmsRaw = $query->get(); 
        $firms = [];

        foreach ($firmsRaw as $firm) {
            $firms[] = [
                'id' => $firm->id,
                'name' => $firm->data['name'] ?? 'İsimsiz Tesis',
                'consultant' => $firm->consultantName(),
                'consultant_user_id' => $firm->consultant_user_id,
                'sector' => $firm->data['sector'] ?? '',
                'status' => $firm->data['status'] ?? 'aktif',
                'contractDate' => $firm->data['contractDate'] ?? null,
                'serviceStartedAt' => $firm->data['serviceStartedAt'] ?? null,
                'serviceEndedAt' => $firm->data['serviceEndedAt'] ?? null,
            ];
        }

        usort($firms, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $consultants = User::where('role', 'employee')->where('active', true)
            ->orderBy('name')->get(['id', 'name', 'username']);

        return Inertia::render('Tesisler', [
            'firms' => $firms,
            'consultants' => $consultants
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'sector' => 'nullable|string|max:255',
            'consultant_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $id = uniqid(); 
        
        $items = [];
        foreach (ComplianceEngine::keys() as $k) {
            $items[$k] = ['s' => null];
        }

        $firmData = [
            'name' => mb_strtoupper(trim($request->name), 'UTF-8'),
            'sector' => mb_strtoupper(trim($request->sector ?? ''), 'UTF-8'),
            'status' => 'aktif',
            'note' => '',
            'contact' => '',
            'profile' => [],
            'items' => $items,
            'updatedAt' => now()->toDateTimeString(),
            'updatedBy' => $user->name
        ];

        $consultantId = $user->role === 'admin' ? $request->integer('consultant_user_id') : $user->id;
        if ($consultantId && !User::where('id', $consultantId)->where('role', 'employee')->where('active', true)->exists()) {
            return response()->json(['error' => 'Atanacak danışman aktif bir kullanıcı olmalıdır.'], 422);
        }

        Firm::create(['id' => $id, 'consultant_user_id' => $consultantId ?: null, 'data' => $firmData]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function show(Request $request, $id)
    {
        $firm = Firm::with('consultant:id,name,username')->findOrFail($id);
        $user = $request->user();

        // YETKİSİZ ERİŞİM KONTROLÜ (Sayfa yönlendirmeli)
        if ($user->role !== 'admin' && !$firm->isAssignedTo($user)) {
            return redirect()->route('firms.index')->with('error', 'Bu tesise erişim yetkiniz yok.');
        }

        $firmData = $firm->data;
        $firmData['id'] = $firm->id;
        $firmData['consultant'] = $firm->consultantName();
        $firmData['consultant_user_id'] = $firm->consultant_user_id;

        if (!isset($firmData['items'])) $firmData['items'] = [];
        foreach (ComplianceEngine::keys() as $k) {
            if (!isset($firmData['items'][$k])) {
                $firmData['items'][$k] = [];
            }
            if (($firmData['items'][$k]['s'] ?? null) === 'surec') {
                $firmData['items'][$k]['s'] = null;
                $firmData['items'][$k]['d'] = null;
                $firmData['items'][$k]['note'] = null;
                unset($firmData['items'][$k]['v']);
            }
            $firmData['items'][$k]['s'] = $firmData['items'][$k]['s'] ?? null;
            $firmData['items'][$k]['d'] = $firmData['items'][$k]['d'] ?? null;
            $firmData['items'][$k]['note'] = $firmData['items'][$k]['note'] ?? null;
        }
        
        if (!isset($firmData['profile'])) $firmData['profile'] = [];
        $firmData['profile']['cedKarari'] = $firmData['profile']['cedKarari'] ?? '';
        $firmData['profile']['cedTarihi'] = $firmData['profile']['cedTarihi'] ?? null;
        if (is_string($firmData['profile']['cedKarari'])
            && ComplianceEngine::isLegacyMissingValue($firmData['profile']['cedKarari'])) {
            $firmData['profile']['cedKarari'] = '';
            $firmData['profile']['cedTarihi'] = null;
        }
        $firmData['profile']['atiksuKurum'] = $firmData['profile']['atiksuKurum'] ?? '';
        $firmData['profile']['izinKonusu'] = $firmData['profile']['izinKonusu'] ?? '';
        $firmData['contractDate'] = $firmData['contractDate'] ?? null;
        $firmData['serviceStartedAt'] = $firmData['serviceStartedAt'] ?? null;
        $firmData['serviceEndedAt'] = $firmData['serviceEndedAt'] ?? null;

        $today = Carbon::today();
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

        $ev = ComplianceEngine::evaluateFirm($firmData, $today, $cfg);

        return Inertia::render('FirmaDetay', [
            'firm' => $firmData,
            'consultants' => $user->role === 'admin'
                ? User::query()->where('role', 'employee')->where('active', true)->orderBy('name')->get(['id', 'name', 'username'])
                : [],
            'evaluations' => $ev,
            'meta' => [
                'obligations' => ComplianceEngine::getAll(),
                'stateL' => ComplianceEngine::STATE_L,
            ],
            'canEdit' => $user->role === 'admin' || $firm->isAssignedTo($user),
            'canManageEngagement' => $user->role === 'admin',
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $firm = Firm::with('consultant:id,name,username')->findOrFail($id);
        $data = $firm->data;

        if ($user->role !== 'admin' && !$firm->isAssignedTo($user)) {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $patch = $request->all();
        $hasConsultantPatch = array_key_exists('consultant_user_id', $patch);
        $hasLegacyConsultantPatch = array_key_exists('consultant', $patch);
        if (($hasConsultantPatch || $hasLegacyConsultantPatch) && $user->role !== 'admin') {
            return response()->json(['error' => 'Danışman atamasını yalnızca yöneticiler değiştirebilir.'], 403);
        }
        if ($hasLegacyConsultantPatch) {
            return response()->json(['error' => 'Danışman ataması kullanıcı kimliğiyle yapılmalıdır.'], 422);
        }

        $request->validate([
            'sector' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'profile' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'consultant_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $oldConsultant = $firm->consultantName();

        if (isset($patch['sector'])) $data['sector'] = mb_strtoupper(trim($patch['sector']), 'UTF-8');
        if (array_key_exists('note', $patch)) $data['note'] = trim((string) $patch['note']);
        if (isset($patch['profile'])) $data['profile'] = array_merge($data['profile'] ?? [], $patch['profile']);
        
        if ($hasConsultantPatch) {
            $newConsultantId = $patch['consultant_user_id'] ?: null;
            $newConsultant = $newConsultantId ? User::query()
                ->where('role', 'employee')
                ->where('active', true)
                ->find($newConsultantId) : null;
            if ($newConsultantId && !$newConsultant) {
                return response()->json(['error' => 'Atanacak danışman aktif bir kullanıcı olmalıdır.'], 422);
            }

            $newConsultantLabel = $newConsultant?->name ?? 'Atanmamış';

            if ((int) $firm->consultant_user_id !== (int) $newConsultantId) {
            
                FirmLog::create([
                    'firm_id' => $firm->id,
                    'user_id' => $user->id,
                    'action' => 'consultant_changed',
                    'description' => "Danışman değiştirildi: '{$oldConsultant}' yerine '{$newConsultantLabel}' atandı.",
                    'from_consultant' => $oldConsultant,
                    'to_consultant' => $newConsultantLabel,
                ]);
            
                $firm->consultant_user_id = $newConsultantId;
            }
        }

        if (isset($patch['items'])) {
            foreach ($patch['items'] as $k => $it) {
                if (!in_array($k, ComplianceEngine::keys())) continue;
                
                $data['items'][$k]['s'] = $it['s'] ?? null;
                $data['items'][$k]['d'] = $it['d'] ?? null;
                $data['items'][$k]['note'] = $it['note'] ?? null;
                
                if ($it['s'] !== 'tarih' && $it['s'] !== 'muaf') {
                    $data['items'][$k]['d'] = null;
                }
                
                $data['items'][$k]['u'] = now()->toDateTimeString();
                $data['items'][$k]['ub'] = $user->name;
            }
        }

        $data['updatedAt'] = now()->toDateTimeString();
        $data['updatedBy'] = $user->name;
        unset($data['consultant']);
        $firm->data = $data;
        $firm->save();

        return response()->json(['ok' => true]);
    }

    public function updateEngagement(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Çalışma tarihlerini yalnızca yönetici değiştirebilir.'], 403);
        }

        $validated = $request->validate([
            'contract_date' => ['nullable', 'date_format:Y-m-d'],
            'started_at' => ['nullable', 'date_format:Y-m-d'],
            'ended_at' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $startedAt = $validated['started_at'] ?? null;
        $endedAt = $validated['ended_at'] ?? null;
        if ($startedAt && $endedAt && $endedAt < $startedAt) {
            return response()->json(['error' => 'Çalışma bitişi, çalışmaya başlama tarihinden önce olamaz.'], 422);
        }

        $firm = Firm::findOrFail($id);
        $data = $firm->data;
        $previousEnd = $data['serviceEndedAt'] ?? null;
        $data['contractDate'] = $validated['contract_date'] ?? null;
        $data['serviceStartedAt'] = $startedAt;
        $data['serviceEndedAt'] = $endedAt;
        $data['status'] = $endedAt ? 'sonlandi' : 'aktif';
        $data['updatedAt'] = now()->toDateTimeString();
        $data['updatedBy'] = $request->user()->name;
        $firm->data = $data;
        $firm->save();

        $period = ($startedAt ? Carbon::parse($startedAt)->format('d.m.Y') : 'başlangıç belirtilmedi')
            . ' - '
            . ($endedAt ? Carbon::parse($endedAt)->format('d.m.Y') : 'devam ediyor');
        FirmLog::create([
            'firm_id' => $firm->id,
            'user_id' => $request->user()->id,
            'action' => $endedAt && !$previousEnd ? 'engagement_ended' : 'engagement_updated',
            'description' => $endedAt && !$previousEnd
                ? "Çalışma sonlandırıldı. Dönem: {$period}."
                : "Çalışma dönemi güncellendi. Dönem: {$period}.",
        ]);

        return response()->json(['ok' => true, 'status' => $data['status']]);
    }

    public function archive(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Sadece yöneticiler arşivleyebilir.'], 403);
        }

        $firm = Firm::findOrFail($id);
        $data = $firm->data;
        
        $currentStatus = $data['status'] ?? 'aktif';
        $newStatus = ($currentStatus === 'aktif') ? 'arsiv' : 'aktif';
        
        $data['status'] = $newStatus;
        $firm->data = $data;
        $firm->save();

        return response()->json(['ok' => true, 'status' => $newStatus]);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Sadece yöneticiler tesis silebilir.'], 403);
        }

        $firm = Firm::findOrFail($id);
        Visit::where('firm_id', $id)->delete();
        FirmLog::where('firm_id', $id)->delete();
        $firm->delete();

        return response()->json(['ok' => true]);
    }

    public function getLogs(Request $request, $id)
    {
        $user = $request->user();
        $firm = Firm::with('consultant:id,name,username')->findOrFail($id);

        if ($user->role !== 'admin' && !$firm->isAssignedTo($user)) {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $logs = FirmLog::with('user')
            ->where('firm_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['logs' => $logs]);
    }
}
