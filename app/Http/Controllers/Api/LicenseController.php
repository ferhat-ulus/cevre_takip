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

class LicenseController extends Controller
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
            
            $izin = $ev['izin'] ?? [];
            $ilUygunluk = $ev['ilUygunluk'] ?? [];

            $states = [$izin['state'] ?? 'ok', $ilUygunluk['state'] ?? 'ok'];
            $worstState = 'ok';
            $ranks = ComplianceEngine::RANK;
            
            foreach ($states as $s) {
                if (isset($ranks[$s]) && $ranks[$s] < $ranks[$worstState]) {
                    $worstState = $s;
                }
            }

            $data[] = [
                'id' => $firm->id,
                'name' => $firmData['name'] ?? 'İsimsiz Tesis',
                'consultant' => $firm->consultantName(),
                'consultant_user_id' => $firm->consultant_user_id,
                'sector' => $firmData['sector'] ?? '-',
                'note' => $firmData['note'] ?? '',
                'izin' => $izin,
                'ilUygunluk' => $ilUygunluk,
                'overallState' => $worstState
            ];
        }

        usort($data, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return Inertia::render('IzinLisans', [
            'firms' => $data,
            'consultants' => $consultants,
            'isAdmin' => $user->role === 'admin'
        ]);
    }

}
