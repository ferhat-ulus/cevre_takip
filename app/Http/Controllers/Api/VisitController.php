<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use App\Models\Firm;
use App\Models\User;
use App\Models\ActivityLog;
use Carbon\Carbon;
use Inertia\Inertia;

class VisitController extends Controller
{
    private const BUSINESS_TIMEZONE = 'Europe/Istanbul';

    public const HOLIDAYS = [
        '2024-01-01', '2024-04-09', '2024-04-10', '2024-04-11', '2024-05-01', '2024-05-19', '2024-06-15', '2024-06-16', '2024-06-17', '2024-06-18', '2024-06-19', '2024-07-15', '2024-08-30', '2024-10-29',
        '2025-01-01', '2025-03-30', '2025-03-31', '2025-04-01', '2025-05-01', '2025-05-19', '2025-06-06', '2025-06-07', '2025-06-08', '2025-06-09', '2025-07-15', '2025-08-30', '2025-10-29',
        '2026-01-01', '2026-03-19', '2026-03-20', '2026-03-21', '2026-03-22', '2026-05-01', '2026-05-19', '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29', '2026-07-15', '2026-08-30', '2026-10-29',
        '2027-01-01', '2027-03-09', '2027-03-10', '2027-03-11', '2027-05-01', '2027-05-19', '2027-05-16', '2027-05-17', '2027-05-18', '2027-05-19', '2027-07-15', '2027-08-30', '2027-10-29',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Danışmanları ID ve İsim olarak çekiyoruz
        $consultants = [];
        if ($user->role === 'admin') {
            $consultants = User::where('role', '!=', 'admin')->select('id', 'name')->get()->toArray();
        }

        $query = Firm::query();
        if ($user->role !== 'admin') {
            $query->where('data->consultant', $user->name);
        }
        $firmsRaw = $query->get();
        
        $firms = [];
        foreach ($firmsRaw as $f) {
            $data = is_array($f->data) ? $f->data : json_decode($f->data, true);
            if ($data) {
                $firms[] = [
                    'id' => $f->id, 
                    'name' => $data['name'] ?? 'İsimsiz Tesis',
                    'consultant' => $data['consultant'] ?? 'Atanmamış'
                ];
            }
        }

        return Inertia::render('ZiyaretTakvimi', [
            'firms' => $firms,
            'consultants' => $consultants,
            'holidays' => self::HOLIDAYS,
            'isAdmin' => $user->role === 'admin',
            'timezone' => self::BUSINESS_TIMEZONE,
        ]);
    }

    public function getEvents(Request $request)
    {
        $user = $request->user();
        $consultantFilter = $request->query('consultant', 'all');
        
        $query = Visit::with(['firm', 'user']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        } elseif ($consultantFilter !== 'all') {
            $query->whereHas('user', function($q) use ($consultantFilter) {
                $q->where('name', $consultantFilter);
            });
        }

        $visits = $query->get();

        $events = $visits->map(function($v) {
            $firmName = ($v->firm && isset($v->firm->data['name'])) ? $v->firm->data['name'] : 'Bilinmeyen Tesis';
            $userName = $v->user ? $v->user->name : 'Bilinmiyor';
            $visitTitle = $v->title ?? 'Ziyaret';
            
            return [
                'id' => $v->id,
                'title' => $firmName . ' - ' . $visitTitle,
                'start' => $v->visit_date,
                'allDay' => true,
                'backgroundColor' => '#4f46e5',
                'borderColor' => '#4f46e5',
                'extendedProps' => [
                    'user_id' => $v->user_id,
                    'notes' => $v->notes ?? '',
                    'consultant' => $userName
                ]
            ];
        });

        return response()->json(['events' => $events]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'firm_id' => 'required|exists:firms,id',
            'visit_date' => 'required|date_format:Y-m-d',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id' // ID kontrolü
        ]);

        $user = $request->user();
        $today = Carbon::now(self::BUSINESS_TIMEZONE)->toDateString();
        if ($request->visit_date <= $today) {
            return response()->json(['error' => 'Ziyaret yalnızca yarın ve sonraki tarihler için planlanabilir.'], 422);
        }

        $firm = Firm::findOrFail($request->firm_id);
        $firmData = is_array($firm->data) ? $firm->data : json_decode($firm->data, true);
        
        $userId = ($user->role === 'admin' && $request->user_id) ? $request->user_id : $user->id;
        $targetUser = User::find($userId);
        $firmConsultant = $firmData['consultant'] ?? '';
        
        if ($targetUser && $targetUser->name !== $firmConsultant) {
            return response()->json(['error' => "UYARI: Seçtiğiniz tesis '{$firmConsultant}' danışmanına aittir. Ziyaret '{$targetUser->name}' adına planlanamaz."], 422);
        }

        $dateString = $request->visit_date; 
        $date = Carbon::parse($dateString);

        if ($date->isWeekend()) {
            return response()->json(['error' => 'Hafta sonları (Cumartesi/Pazar) ziyaret planlanamaz.'], 422);
        }

        if (in_array($dateString, self::HOLIDAYS)) {
            return response()->json(['error' => 'Seçtiğiniz gün resmi tatildir, ziyaret planlanamaz.'], 422);
        }

        $existingVisit = Visit::where('user_id', $userId)->whereDate('visit_date', $dateString)->first();
        if ($existingVisit) {
            $existingFirmName = ($existingVisit->firm && isset($existingVisit->firm->data['name'])) ? $existingVisit->firm->data['name'] : 'başka bir tesis';
            return response()->json(['error' => "Bu danışmanın seçilen günde zaten bir ziyareti var: $existingFirmName"], 422);
        }

        Visit::create([
            'firm_id' => $request->firm_id,
            'user_id' => $userId,
            'visit_date' => $dateString,
            'title' => $request->title ?? 'Tesis Ziyareti',
            'notes' => $request->notes
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'visit_created',
            'description' => "Ziyaret planlandı: {$firmData['name']} / Tarih: " . Carbon::parse($dateString)->format('d.m.Y'),
            'ip_address' => $request->ip()
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $user = $request->user();

        if ($user->role !== 'admin' && $visit->user_id !== $user->id) {
            return response()->json(['error' => 'Bu ziyareti silme yetkiniz yok.'], 403);
        }

        $visitDate = Carbon::parse($visit->visit_date)->toDateString();
        $today = Carbon::now(self::BUSINESS_TIMEZONE)->toDateString();

        if ($visitDate < $today) {
            return response()->json(['error' => 'Geçmiş tarihli ziyaretler silinemez.'], 422);
        }

        if ($visitDate === $today && Carbon::now(self::BUSINESS_TIMEZONE)->hour >= 16) {
            return response()->json(['error' => 'Bugün tarihli ziyaretler saat 16.00’dan sonra silinemez.'], 422);
        }

        $visit->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'visit_deleted',
            'description' => "Ziyaret kaydı silindi. ID: {$id}",
            'ip_address' => $request->ip()
        ]);

        return response()->json(['ok' => true]);
    }
}