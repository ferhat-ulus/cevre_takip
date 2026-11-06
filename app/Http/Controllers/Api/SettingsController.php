<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Config;
use App\Models\ActivityLog;
use App\Services\ComplianceEngine;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz yok.');
        }

        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

        return Inertia::render('Ayarlar', [
            'config' => $cfg,
            'obligations' => ComplianceEngine::getAll()
        ]);
    }

    public function update(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $data = $request->all();
        $cfgRow = Config::find('main');
        
        $cfg = [
            'surecDays' => (int)($data['surecDays'] ?? 90),
            'staleDays' => (int)($data['staleDays'] ?? 30),
            'reviewDays' => (int)($data['reviewDays'] ?? 30),
            'viewAll' => (bool)($data['viewAll'] ?? false),
            'ob' => $cfgRow ? ($cfgRow->v['ob'] ?? []) : []
        ];

        foreach ($data['ob'] ?? [] as $key => $values) {
            if (!in_array($key, ComplianceEngine::keys())) continue;
            
            $obData = [];
            if (isset($values['p']) && $values['p'] !== null && $values['p'] !== '') $obData['p'] = (int)$values['p'];
            if (isset($values['red'])) $obData['red'] = (int)$values['red'];
            if (isset($values['amber'])) $obData['amber'] = (int)$values['amber'];
            
            if (!empty($obData)) $cfg['ob'][$key] = $obData;
        }

        Config::updateOrCreate(['k' => 'main'], ['v' => $cfg]);
        ComplianceEngine::clearObligationsCache();

        if (isset($data['new_obligation'])) {
            $newOb = $data['new_obligation'];
            $key = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $newOb['k'] ?? ''));
            
            if (!empty($key) && !in_array($key, ComplianceEngine::keys())) {
                $customRow = Config::find('custom_obligations');
                $customList = $customRow ? $customRow->v : [];
                if (!is_array($customList)) $customList = [];

                $customList[] = [
                    'k' => $key,
                    'n' => mb_strtoupper($newOb['n'], 'UTF-8'),
                    's' => mb_strtoupper($newOb['s'] ?? $newOb['n'], 'UTF-8'),
                    'g' => mb_strtoupper($newOb['g'], 'UTF-8'),
                    'kind' => 'periodic',
                    'p' => (int)($newOb['p'] ?? 365),
                    'red' => (int)($newOb['red'] ?? 30),
                    'amber' => (int)($newOb['amber'] ?? 60),
                ];
                
                Config::updateOrCreate(['k' => 'custom_obligations'], ['v' => $customList]);

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'obligation_added',
                    'description' => "Yeni mevzuat maddesi eklendi: {$newOb['n']}",
                    'ip_address' => $request->ip()
                ]);
            }
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'settings_updated',
            'description' => 'Sistem süreleri ve kritik eşik ayarları güncellendi.',
            'ip_address' => $request->ip()
        ]);

        return response()->json(['ok' => true, 'message' => 'Ayarlar başarıyla kaydedildi.']);
    }

    // YENİ EKLENEN METOT: Özel Mevzuat Silme
    public function destroyObligation(Request $request, $key)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $customRow = Config::find('custom_obligations');
        if (!$customRow || !is_array($customRow->v)) {
            return response()->json(['error' => 'Silinecek özel mevzuat bulunamadı.'], 404);
        }

        $customList = $customRow->v;
        $newList = [];
        $deleted = false;
        $deletedName = '';

        foreach ($customList as $ob) {
            if ($ob['k'] === $key) {
                $deleted = true;
                $deletedName = $ob['n'];
            } else {
                $newList[] = $ob;
            }
        }

        if (!$deleted) {
            return response()->json(['error' => 'Bu mevzuat veritabanında bulunamadı (Kodun içindeki statik mevzuatlar silinemez).'], 403);
        }

        Config::updateOrCreate(['k' => 'custom_obligations'], ['v' => $newList]);
        ComplianceEngine::clearObligationsCache();

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'obligation_deleted',
            'description' => "Mevzuat maddesi silindi: {$deletedName}",
            'ip_address' => $request->ip()
        ]);

        return response()->json(['ok' => true]);
    }
}