<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Firm;
use App\Models\Config;
use App\Models\Visit;
use App\Services\ComplianceEngine;
use App\Services\BusinessCalendar;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, BusinessCalendar $calendar)
    {
        $user = $request->user();
        $today = Carbon::today(BusinessCalendar::TIMEZONE);
        
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

        // Veritabanı seviyesinde yetki filtresi (Performans artışı)
        $query = Firm::query()->with('consultant:id,name')->visibleTo($user);
        
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
            $firmData['consultant'] = $firm->consultantName();
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

            $consultantName = $firm->consultantName();
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
            'calendarReminder' => $user->role === 'admin' ? $calendar->reminder() : null,
            'visitPlanningReminder' => $this->visitPlanningReminder($user, $firmsRaw),
        ]);
    }

    /**
     * Takip eden aya ait ziyaret planı, her danışmanın kendi aktif tesisleri
     * üzerinden değerlendirilir. Bir tesis için ay içinde en az bir ziyaret
     * bulunması o tesisin planlandığı anlamına gelir.
     */
    private function visitPlanningReminder($user, Collection $firms): array
    {
        $now = CarbonImmutable::now(BusinessCalendar::TIMEZONE);
        $targetMonth = $now->addMonthNoOverflow()->startOfMonth();

        if ($now->day < 20) {
            return ['visible' => false];
        }

        $assignedFirms = $firms
            ->filter(fn (Firm $firm) => $firm->consultant_user_id && !ComplianceEngine::isEngagementClosed($firm->data))
            ->filter(fn (Firm $firm) => $user->role === 'admin' || (int) $firm->consultant_user_id === (int) $user->id)
            ->values();

        if ($assignedFirms->isEmpty()) {
            return ['visible' => false];
        }

        $plannedFirmKeys = Visit::query()
            ->whereBetween('visit_date', [$targetMonth->toDateString(), $targetMonth->endOfMonth()->toDateString()])
            ->get(['firm_id', 'user_id'])
            ->map(fn (Visit $visit) => $visit->user_id . ':' . $visit->firm_id)
            ->flip();

        $missingFirms = $assignedFirms
            ->filter(fn (Firm $firm) => !$plannedFirmKeys->has($firm->consultant_user_id . ':' . $firm->id))
            ->values();

        if ($missingFirms->isEmpty()) {
            return ['visible' => false];
        }

        $monthLabel = $targetMonth->locale('tr')->translatedFormat('F Y');
        $consultants = $missingFirms
            ->groupBy('consultant_user_id')
            ->map(function (Collection $consultantFirms) {
                $first = $consultantFirms->first();

                return [
                    'name' => $first->consultantName(),
                    'missingCount' => $consultantFirms->count(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'visible' => true,
            'monthLabel' => ucfirst($monthLabel),
            'missingCount' => $missingFirms->count(),
            'consultants' => $consultants,
        ];
    }
}
