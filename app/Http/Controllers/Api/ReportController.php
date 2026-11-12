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
    private const PDF_DETAIL_LIMIT = 300;

    private function getReportData($user, $consultantFilter = 'all', $includeDetailedTasks = false)
    {
        $today = Carbon::today();
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

        $consultants = User::where('role', 'employee')->where('active', true)->orderBy('name')->get(['id', 'name', 'username']);

        // PERFORMANS: Veritabanı seviyesinde filtreleme
        $query = Firm::query()->with('consultant:id,name')->visibleTo($user);
        if ($user->role === 'admin' && $consultantFilter !== 'all') {
            $query->where('consultant_user_id', (int) $consultantFilter);
        }
        
        $stats = ['overdue' => 0, 'crit' => 0, 'soon' => 0, 'ok' => 0, 'missing' => 0];
        $consultantStats = [];
        $consultantLabels = [];
        $detailedTasks = [];
        $detailedTaskCount = 0;
        $detailedTasksSorted = false;
        $riskyFirms = [];
        $workItems = [];

        foreach ($query->lazyById(100, 'id') as $firm) {
            $firmData = $firm->data;
            $firmData['consultant'] = $firm->consultantName();
            if (ComplianceEngine::isEngagementClosed($firmData)) {
                continue;
            }
            $firmData['id'] = $firm->id;
            $consultant = $firm->consultantName();
            $consultantId = $firm->consultant_user_id ? (string) $firm->consultant_user_id : 'unassigned';
            $consultantLabels[$consultantId] = $consultant;
            
            if (!isset($consultantStats[$consultantId])) {
                $consultantStats[$consultantId] = ['overdue' => 0, 'crit' => 0, 'soon' => 0, 'ok' => 0, 'missing' => 0];
            }

            $ev = ComplianceEngine::evaluateFirm($firmData, $today, $cfg);
            $firmIssues = [];
            foreach ($ev as $key => $e) {
                if (isset($stats[$e['state']])) {
                    $stats[$e['state']]++;
                    $consultantStats[$consultantId][$e['state']]++;
                }

                if ($includeDetailedTasks && !in_array($e['state'], ['ok', 'muaf'], true)) {
                    $item = $firmData['items'][$key] ?? [];
                    $task = [
                        'firm_name' => $firmData['name'] ?? 'İsimsiz',
                        'consultant' => $consultant,
                        'consultant_user_id' => $firm->consultant_user_id,
                        'topic' => ComplianceEngine::obl($key)['n'],
                        'state' => $e['state'],
                        'state_label' => ComplianceEngine::STATE_L[$e['state']] ?? $e['state'],
                        'days' => $e['days'],
                        'due' => $e['due'] ? Carbon::parse($e['due'])->format('d.m.Y') : '-',
                        'note' => $item['note'] ?? '',
                    ];
                    $detailedTaskCount++;

                    if (count($detailedTasks) < self::PDF_DETAIL_LIMIT) {
                        $detailedTasks[] = $task;
                    } else {
                        if (!$detailedTasksSorted) {
                            usort($detailedTasks, self::compareDetailedTasks(...));
                            $detailedTasksSorted = true;
                        }

                        $lastIndex = count($detailedTasks) - 1;
                        if (self::compareDetailedTasks($task, $detailedTasks[$lastIndex]) < 0) {
                            $low = 0;
                            $high = $lastIndex;
                            while ($low < $high) {
                                $middle = intdiv($low + $high, 2);
                                if (self::compareDetailedTasks($task, $detailedTasks[$middle]) < 0) {
                                    $high = $middle;
                                } else {
                                    $low = $middle + 1;
                                }
                            }
                            array_splice($detailedTasks, $low, 0, [$task]);
                            array_pop($detailedTasks);
                        }
                    }
                }

                if (in_array($e['state'], ['missing', 'overdue', 'crit', 'soon'], true)) {
                    $workItems[] = [
                        'firm_id' => $firm->id,
                        'firm_name' => $firmData['name'] ?? 'İsimsiz',
                        'consultant' => $consultant,
                        'consultant_user_id' => $firm->consultant_user_id,
                        'topic' => ComplianceEngine::obl($key)['n'],
                        'state' => $e['state'],
                        'state_label' => ComplianceEngine::STATE_L[$e['state']] ?? $e['state'],
                        'days' => $e['days'],
                        'due' => $e['due'] ? Carbon::parse($e['due'])->format('d.m.Y') : null,
                    ];
                    $firmIssues[] = [
                        'topic' => ComplianceEngine::obl($key)['n'],
                        'state' => ComplianceEngine::STATE_L[$e['state']],
                        'days' => $e['days'],
                        'due' => $e['due'] ? Carbon::parse($e['due'])->format('d.m.Y') : '-',
                        'color' => $e['state'],
                    ];
                }
            }

            if ($firmIssues) {
                $riskyFirms[] = [
                    'name' => $firmData['name'] ?? 'İsimsiz',
                    'consultant' => $consultant,
                    'risky_count' => count($firmIssues),
                    'issues' => $firmIssues,
                ];
            }
        }

        if ($detailedTasks && !$detailedTasksSorted) {
            usort($detailedTasks, self::compareDetailedTasks(...));
        }
        usort($riskyFirms, fn($a, $b) => $b['risky_count'] <=> $a['risky_count']);
        usort($workItems, self::compareDetailedTasks(...));

        return [
            'stats' => $stats,
            'consultantStats' => $consultantStats,
            'consultantLabels' => $consultantLabels,
            'riskyFirms' => array_slice($riskyFirms, 0, 10),
            'workItems' => $workItems,
            'detailedTasks' => $detailedTasks,
            'detailedTaskCount' => $detailedTaskCount,
            'detailedTaskLimit' => self::PDF_DETAIL_LIMIT,
            'consultants' => $consultants
        ];
    }

    private static function compareDetailedTasks(array $a, array $b): int
    {
        $rank = (ComplianceEngine::RANK[$a['state']] ?? 99) <=> (ComplianceEngine::RANK[$b['state']] ?? 99);
        if ($rank !== 0) return $rank;
        $days = ($a['days'] ?? PHP_INT_MAX) <=> ($b['days'] ?? PHP_INT_MAX);
        if ($days !== 0) return $days;
        return strcmp($a['firm_name'], $b['firm_name']);
    }

    public function index(Request $request)
    {
        $data = $this->getReportData($request->user(), 'all');
        return Inertia::render('Raporlar', $data);
    }

    public function exportPdf(Request $request)
    {
        $consultantFilter = $request->query('consultant', 'all');
        
        set_time_limit(300);
        $data = $this->getReportData($request->user(), $consultantFilter, true);
        $data['today'] = Carbon::today()->format('d.m.Y');
        $data['userName'] = $request->user()->name;

        $pdf = Pdf::loadView('reports.pdf', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('cevre-takip-raporu-' . Carbon::today()->format('Y-m-d') . '.pdf');
    }
}
