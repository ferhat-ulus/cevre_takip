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
        
        $query = Firm::query();
        if ($user->role !== 'admin') {
            $query->where('data->consultant', $user->name);
        }
        
        $firmsRaw = $query->get(); 
        $firms = [];

        foreach ($firmsRaw as $firm) {
            $firms[] = [
                'id' => $firm->id,
                'name' => $firm->data['name'] ?? 'İsimsiz Tesis',
                'consultant' => $firm->data['consultant'] ?? 'Atanmamış',
                'sector' => $firm->data['sector'] ?? '',
                'status' => $firm->data['status'] ?? 'aktif'
            ];
        }

        usort($firms, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $consultants = User::where('role', 'employee')->pluck('name');

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
            'consultant' => 'nullable|string|max:255',
        ]);

        $id = uniqid(); 
        
        $items = [];
        foreach (ComplianceEngine::keys() as $k) {
            $items[$k] = ['s' => null];
        }

        $firmData = [
            'name' => mb_strtoupper(trim($request->name), 'UTF-8'),
            'consultant' => $user->role === 'admin' ? mb_strtoupper(trim($request->consultant ?? ''), 'UTF-8') : $user->name,
            'sector' => mb_strtoupper(trim($request->sector ?? ''), 'UTF-8'),
            'status' => 'aktif',
            'note' => '',
            'contact' => '',
            'profile' => [],
            'items' => $items,
            'updatedAt' => now()->toDateTimeString(),
            'updatedBy' => $user->name
        ];

        Firm::create(['id' => $id, 'data' => $firmData]);

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function show(Request $request, $id)
    {
        $firm = Firm::findOrFail($id);
        $user = $request->user();

        // YETKİSİZ ERİŞİM KONTROLÜ (Sayfa yönlendirmeli)
        if ($user->role !== 'admin' && ($firm->data['consultant'] ?? '') !== $user->name) {
            return redirect()->route('firms.index')->with('error', 'Bu tesise erişim yetkiniz yok.');
        }

        $firmData = $firm->data;
        $firmData['id'] = $firm->id;

        if (!isset($firmData['items'])) $firmData['items'] = [];
        foreach (ComplianceEngine::keys() as $k) {
            if (!isset($firmData['items'][$k])) {
                $firmData['items'][$k] = [];
            }
            $firmData['items'][$k]['s'] = $firmData['items'][$k]['s'] ?? null;
            $firmData['items'][$k]['d'] = $firmData['items'][$k]['d'] ?? null;
            $firmData['items'][$k]['note'] = $firmData['items'][$k]['note'] ?? null;
        }
        
        if (!isset($firmData['profile'])) $firmData['profile'] = [];
        $firmData['profile']['cedKarari'] = $firmData['profile']['cedKarari'] ?? '';
        $firmData['profile']['cedTarihi'] = $firmData['profile']['cedTarihi'] ?? null;
        $firmData['profile']['atiksuKurum'] = $firmData['profile']['atiksuKurum'] ?? '';
        $firmData['profile']['izinKonusu'] = $firmData['profile']['izinKonusu'] ?? '';

        $today = Carbon::today();
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

        $ev = ComplianceEngine::evaluateFirm($firmData, $today, $cfg);

        return Inertia::render('FirmaDetay', [
            'firm' => $firmData,
            'evaluations' => $ev,
            'meta' => [
                'obligations' => ComplianceEngine::getAll(),
                'stateL' => ComplianceEngine::STATE_L,
            ],
            'canEdit' => $user->role === 'admin' || ($firmData['consultant'] ?? '') === $user->name
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $firm = Firm::findOrFail($id);
        $data = $firm->data;

        if ($user->role !== 'admin' && ($data['consultant'] ?? '') !== $user->name) {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $patch = $request->all();
        $oldConsultant = $data['consultant'] ?? 'Atanmamış';

        if (isset($patch['sector'])) $data['sector'] = mb_strtoupper(trim($patch['sector']), 'UTF-8');
        if (isset($patch['profile'])) $data['profile'] = array_merge($data['profile'] ?? [], $patch['profile']);
        
        if (isset($patch['consultant']) && $patch['consultant'] !== $oldConsultant) {
            $newConsultant = $patch['consultant'] ?: 'Atanmamış';
            
            FirmLog::create([
                'firm_id' => $firm->id,
                'user_id' => $user->id,
                'action' => 'consultant_changed',
                'description' => "Danışman değiştirildi: '{$oldConsultant}' yerine '{$newConsultant}' atandı."
            ]);
            
            $data['consultant'] = mb_strtoupper(trim($patch['consultant']), 'UTF-8');
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
        $firm->data = $data;
        $firm->save();

        return response()->json(['ok' => true]);
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
        $firm = Firm::findOrFail($id);

        if ($user->role !== 'admin' && ($firm->data['consultant'] ?? '') !== $user->name) {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $logs = FirmLog::with('user')
            ->where('firm_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['logs' => $logs]);
    }
}