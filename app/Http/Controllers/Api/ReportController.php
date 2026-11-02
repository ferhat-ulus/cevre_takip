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
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    private function getReportData($user, $consultantFilter = 'all')
    {
        $today = Carbon::today();
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

        $consultants = User::where('role', 'employee')->pluck('name');

        // PERFORMANS: Veritabanı seviyesinde filtreleme
        $query = Firm::query();
        
        if ($user->role !== 'admin') {
            $query->where('data->consultant', $user->name);
        } elseif ($consultantFilter !== 'all') {
            $query->where('data->consultant', $consultantFilter);
        }
        
        $firmsRaw = $query->get();

        $firms = [];
        $evs = [];
        $stats = ['overdue' => 0, 'crit' => 0, 'soon' => 0, 'ok' => 0];
        $consultantStats = [];

        foreach ($firmsRaw as $firm) {
            $firmData = $firm->data;
            $firmData['id'] = $firm->id;
            $consultant = $firmData['consultant'] ?? 'Atanmamış';
            
            if (!isset($consultantStats[$consultant])) {
                $consultantStats[$consultant] = ['overdue' => 0, 'crit' => 0, 'soon' => 0, 'ok' => 0];
            }

            $ev = ComplianceEngine::evaluateFirm($firmData, $today, $cfg);
            $firms[] = $firmData;
            $evs[$firm->id] = $ev;
            
            foreach ($ev as $e) {
                if ($e['state'] === 'overdue') { $stats['overdue']++; $consultantStats[$consultant]['overdue']++; }
                if ($e['state'] === 'crit') { $stats['crit']++; $consultantStats[$consultant]['crit']++; }
                if ($e['state'] === 'soon') { $stats['soon']++; $consultantStats[$consultant]['soon']++; }
                if ($e['state'] === 'ok') { $stats['ok']++; $consultantStats[$consultant]['ok']++; }
            }
        }

        $taskList = ComplianceEngine::taskList($firms, $evs);
        $detailedTasks = [];
        
        foreach ($taskList as $item) {
            $f = $item[0]; $k = $item[1]; $e = $item[2];
            $it = $f['items'][$k] ?? [];
            
            $detailedTasks[] = [
                'firm_name' => $f['name'],
                'consultant' => $f['consultant'] ?? 'Atanmamış',
                'topic' => ComplianceEngine::obl($k)['n'],
                'state' => $e['state'],
                'state_label' => ComplianceEngine::STATE_L[$e['state']],
                'days' => $e['days'],
                'due' => $e['due'] ? Carbon::parse($e['due'])->format('d.m.Y') : '-',
                'note' => $it['note'] ?? ''
            ];
        }

        $riskyFirms = [];
        foreach ($firms as $f) {
            $firmIssues = [];
            foreach ($evs[$f['id']] as $k => $e) {
                if (in_array($e['state'], ['overdue', 'crit', 'soon'])) {
                    $firmIssues[] = [
                        'topic' => ComplianceEngine::obl($k)['n'],
                        'state' => ComplianceEngine::STATE_L[$e['state']],
                        'days' => $e['days'],
                        'due' => $e['due'] ? Carbon::parse($e['due'])->format('d.m.Y') : '-',
                        'color' => $e['state']
                    ];
                }
            }
            if (count($firmIssues) > 0) {
                $riskyFirms[] = [
                    'name' => $f['name'],
                    'consultant' => $f['consultant'] ?? 'Atanmamış',
                    'risky_count' => count($firmIssues),
                    'issues' => $firmIssues
                ];
            }
        }
        usort($riskyFirms, fn($a, $b) => $b['risky_count'] <=> $a['risky_count']);

        return [
            'stats' => $stats,
            'consultantStats' => $consultantStats,
            'riskyFirms' => array_slice($riskyFirms, 0, 10),
            'detailedTasks' => $detailedTasks,
            'consultants' => $consultants
        ];
    }

    public function index(Request $request)
    {
        $data = $this->getReportData($request->user(), 'all');
        return Inertia::render('Raporlar', $data);
    }

    public function exportPdf(Request $request)
    {
        $consultantFilter = $request->query('consultant', 'all');
        
        $data = $this->getReportData($request->user(), $consultantFilter);
        $data['today'] = Carbon::today()->format('d.m.Y');
        $data['userName'] = $request->user()->name;

        $pdf = Pdf::loadView('reports.pdf', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('cevre-takip-raporu-' . Carbon::today()->format('Y-m-d') . '.pdf');
    }
}