<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Firm;
use App\Models\Config;
use App\Models\User;
use App\Services\ComplianceEngine;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();
        
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

        // Veritabanı seviyesinde yetki filtresi (Performans artışı)
        $query = Firm::query();
        if ($user->role !== 'admin') {
            $query->where('data->consultant', $user->name);
        }
        
        $firmsRaw = $query->get();

        // 'surec' istatistiği diziden çıkarıldı
        $stats = ['overdue' => 0, 'crit' => 0, 'soon' => 0];
        $tasks = [];

        foreach ($firmsRaw as $firm) {
            $firmData = $firm->data;
            $firmData['id'] = $firm->id;
            
            $ev = ComplianceEngine::evaluateFirm($firmData, $today, $cfg);
            
            foreach ($ev as $k => $e) {
                // Sadece gecikmiş, kritik ve yaklaşan durumları istatistiğe ekle
                if (isset($stats[$e['state']])) {
                    $stats[$e['state']]++;
                }

                // Sadece gecikmiş, kritik ve yaklaşan durumları görev listesine ekle ('surec' çıkarıldı)
                if (in_array($e['state'], ['overdue', 'crit', 'soon'])) {
                    $tasks[] = [
                        'firm_id'    => $firm->id,
                        'firma'      => $firmData['name'] ?? 'İsimsiz',
                        'consultant' => $firmData['consultant'] ?? 'Atanmamış',
                        'konu'       => ComplianceEngine::obl($k)['n'],
                        'kalan'      => $e['days'] !== null ? $e['days'] . ' gün' : 'Belirsiz',
                        'durum'      => ComplianceEngine::STATE_L[$e['state']],
                        'state'      => $e['state'],
                        'days'       => $e['days'] ?? 9999, // Sıralama için
                    ];
                }
            }
        }

        // Öncelik sırasına göre diz (Gecikmiş -> Kritik -> Yaklaşan)
        usort($tasks, function($a, $b) {
            $rankA = ComplianceEngine::RANK[$a['state']] ?? 99;
            $rankB = ComplianceEngine::RANK[$b['state']] ?? 99;
            if ($rankA !== $rankB) return $rankA <=> $rankB;
            return $a['days'] <=> $b['days'];
        });

        $consultants = User::where('role', 'employee')->pluck('name');

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'tasks' => array_slice($tasks, 0, 15),
            'consultants' => $consultants
        ]);
    }
}