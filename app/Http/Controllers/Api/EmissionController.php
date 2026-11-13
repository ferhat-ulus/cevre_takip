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

class EmissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();
        
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);

        $consultants = [];
        if ($user->role === 'admin') {
            $consultants = User::activeConsultants()->orderBy('name')->get(['id', 'name', 'username']);
        }

        // PERFORMANS: Veritabanı seviyesinde filtreleme
        $query = Firm::query()->with('consultant:id,name')->visibleTo($user);
        $firmsRaw = $query->get();
        
        $data = [];

        foreach ($firmsRaw as $firm) {
            $firmData = $firm->data;
            $firmData['consultant'] = $firm->consultantName();
            if (ComplianceEngine::isEngagementClosed($firmData)) {
                continue;
            }
            $firmData['id'] = $firm->id;
            
            $ev = ComplianceEngine::evaluateFirm($firmData, $today, $cfg);
            
            $emisyonOlcum = $ev['emisyonOlcum'] ?? [];
            $emisyonRapor = $ev['emisyonRapor'] ?? [];
            $emisyonTeyitRaporu = $ev['emisyonTeyitRaporu'] ?? [];
            $items = $firmData['items'] ?? [];
            $permitMeasurementDate = ($items['izneEsasEmisyonOlcum']['s'] ?? null) === 'tarih'
                ? ($items['izneEsasEmisyonOlcum']['d'] ?? null)
                : null;
            $confirmationDate = ($items['emisyonOlcum']['s'] ?? null) === 'tarih'
                ? ($items['emisyonOlcum']['d'] ?? null)
                : null;
            $reportDate = ($items['emisyonRapor']['s'] ?? null) === 'tarih'
                ? ($items['emisyonRapor']['d'] ?? null)
                : null;
            $confirmationReportDate = ($items['emisyonTeyitRaporu']['s'] ?? null) === 'tarih'
                ? ($items['emisyonTeyitRaporu']['d'] ?? null)
                : null;

            $states = [
                $emisyonOlcum['state'] ?? 'missing',
                $emisyonRapor['state'] ?? 'missing',
                $emisyonTeyitRaporu['state'] ?? 'missing',
            ];
            $worstState = 'ok';
            $ranks = ComplianceEngine::RANK;
            
            foreach ($states as $s) {
                if (isset($ranks[$s]) && $ranks[$s] < $ranks[$worstState]) {
                    $worstState = $s;
                }
            }
            if ($worstState === 'missing') {
                $worstState = 'overdue';
            }

            $data[] = [
                'id' => $firm->id,
                'name' => $firmData['name'] ?? 'İsimsiz Tesis',
                'consultant' => $firm->consultantName(),
                'consultant_user_id' => $firm->consultant_user_id,
                'sector' => $firmData['sector'] ?? '-',
                'note' => $firmData['note'] ?? '',
                'izneEsasEmisyonOlcumDate' => $permitMeasurementDate,
                'emisyonOlcum' => array_merge($emisyonOlcum, [
                    'recordedDate' => $confirmationDate,
                    'basisDate' => $emisyonOlcum['basisDate'] ?? $permitMeasurementDate,
                    'isPermitBasis' => !$confirmationDate && !empty($emisyonOlcum['basisDate']),
                ]),
                'emisyonRapor' => array_merge($emisyonRapor, ['recordedDate' => $reportDate]),
                'emisyonTeyitRaporu' => array_merge($emisyonTeyitRaporu, [
                    'recordedDate' => $confirmationReportDate,
                    'basisDate' => $emisyonTeyitRaporu['basisDate'] ?? $reportDate,
                    'isPermitBasis' => !$confirmationReportDate && !empty($emisyonTeyitRaporu['basisDate']),
                ]),
                'overallState' => $worstState
            ];
        }

        usort($data, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return Inertia::render('Emission', [
            'firms' => $data,
            'consultants' => $consultants,
            'isAdmin' => $user->role === 'admin'
        ]);
    }
}
