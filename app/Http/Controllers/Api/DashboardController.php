<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Firm;
use App\Models\Config;
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

        $stats = ['overdue' => 0, 'crit' => 0, 'soon' => 0, 'missing' => 0];
        $firmSummary = [
            'total' => 0,
            'ek1' => 0,
            'ek2' => 0,
            'scopeOutside' => 0,
        ];
        $consultants = [];

        foreach ($firmsRaw as $firm) {
            $firmData = $firm->data;
            if (ComplianceEngine::isEngagementClosed($firmData)) {
                continue;
            }
            $firmData['id'] = $firm->id;
            $firmSummary['total']++;

            $permitValue = $firmData['profile']['izinKonusu'] ?? '';
            $permit = is_string($permitValue) ? mb_strtoupper(trim($permitValue), 'UTF-8') : '';
            $permit = preg_replace('/[\s._-]+/u', '', $permit) ?? $permit;
            if (str_contains($permit, 'KAPSAMDIŞI')) {
                $firmSummary['scopeOutside']++;
            } elseif (str_contains($permit, 'EK1')) {
                $firmSummary['ek1']++;
            } elseif (str_contains($permit, 'EK2')) {
                $firmSummary['ek2']++;
            }

            $consultantName = trim((string) ($firmData['consultant'] ?? ''));
            if ($consultantName === '') {
                $consultantName = 'Atanmamış';
            }
            if (!isset($consultants[$consultantName])) {
                $consultants[$consultantName] = [
                    'name' => $consultantName,
                    'firmCount' => 0,
                    'overdue' => 0,
                    'crit' => 0,
                    'soon' => 0,
                    'missing' => 0,
                    'tasks' => [],
                ];
            }
            $consultants[$consultantName]['firmCount']++;

            $ev = ComplianceEngine::evaluateFirm($firmData, $today, $cfg);

            foreach ($ev as $k => $e) {
                $state = $e['state'];
                if (!isset($stats[$state])) {
                    continue;
                }
                $stats[$state]++;
                $consultants[$consultantName][$state]++;

                if ($state === 'missing') {
                    continue;
                }

                $consultants[$consultantName]['tasks'][] = [
                    'firm_id' => $firm->id,
                    'firma' => $firmData['name'] ?? 'İsimsiz',
                    'konu' => ComplianceEngine::obl($k)['n'],
                    'durum' => ComplianceEngine::STATE_L[$state],
                    'state' => $state,
                    'days' => $e['days'],
                ];
            }
        }

        foreach ($consultants as &$consultant) {
            usort($consultant['tasks'], function ($a, $b) {
                $rankA = ComplianceEngine::RANK[$a['state']] ?? 99;
                $rankB = ComplianceEngine::RANK[$b['state']] ?? 99;
                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }
                return ($a['days'] ?? PHP_INT_MAX) <=> ($b['days'] ?? PHP_INT_MAX);
            });
            $consultant['tasks'] = array_slice($consultant['tasks'], 0, 8);
        }
        unset($consultant);

        $consultants = array_values($consultants);
        usort($consultants, function ($a, $b) {
            foreach (['overdue', 'crit', 'soon', 'missing'] as $key) {
                if ($a[$key] !== $b[$key]) {
                    return $b[$key] <=> $a[$key];
                }
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'firmSummary' => $firmSummary,
            'consultants' => $consultants,
        ]);
    }
}