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
            $consultants = User::where('role', 'employee')->select('name')->distinct()->pluck('name');
        }

        // PERFORMANS: Veritabanı seviyesinde filtreleme
        $query = Firm::query();
        if ($user->role !== 'admin') {
            $query->where('data->consultant', $user->name);
        }
        $firmsRaw = $query->get();
        
        $data = [];

        foreach ($firmsRaw as $firm) {
            $firmData = $firm->data;
            $firmData['id'] = $firm->id;
            
            $ev = ComplianceEngine::evaluateFirm($firmData, $today, $cfg);
            
            $emisyonOlcum = $ev['emisyonOlcum'] ?? [];
            $emisyonRapor = $ev['emisyonRapor'] ?? [];

            $states = [$emisyonOlcum['state'] ?? 'ok', $emisyonRapor['state'] ?? 'ok'];
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
                'consultant' => $firmData['consultant'] ?? 'Atanmamış',
                'sector' => $firmData['sector'] ?? '-',
                'note' => $firmData['note'] ?? '',
                'emisyonOlcum' => $emisyonOlcum,
                'emisyonRapor' => $emisyonRapor,
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