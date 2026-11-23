<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit;
use App\Models\Firm;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\VisitApprovalRequest;
use App\Services\BusinessCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VisitController extends Controller
{
    public function __construct(private readonly BusinessCalendar $calendar)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Danışmanları ID ve İsim olarak çekiyoruz
        $consultants = [];
        if ($user->role === 'admin') {
            $consultants = User::activeConsultants()->select('id', 'name', 'username')->orderBy('name')->get()->toArray();
        }

        $query = Firm::query()->with('consultant:id,name')->visibleTo($user);
        $firmsRaw = $query->get();
        
        $firms = [];
        foreach ($firmsRaw as $f) {
            $data = is_array($f->data) ? $f->data : json_decode($f->data, true);
            if ($data) {
                $firms[] = [
                    'id' => $f->id, 
                    'name' => $data['name'] ?? 'İsimsiz Tesis',
                    'consultant' => $f->consultantName(),
                    'consultant_user_id' => $f->consultant_user_id,
                ];
            }
        }

        $approvalRequests = $user->role === 'admin'
            ? VisitApprovalRequest::with(['firm', 'assignedUser:id,name', 'requestedBy:id,name'])
                ->where('status', 'pending')->orderBy('visit_date')->orderBy('created_at')->get()
                ->map(function (VisitApprovalRequest $request) {
                    $firmData = is_array($request->firm?->data) ? $request->firm->data : [];

                    return [
                        'id' => $request->id,
                        'firm_id' => $request->firm_id,
                        'firm_name' => $firmData['name'] ?? 'Bilinmeyen tesis',
                        'assigned_user_id' => $request->assigned_user_id,
                        'assigned_user' => $request->assignedUser?->name ?? 'Bilinmiyor',
                        'requested_by' => $request->requestedBy?->name ?? 'Bilinmiyor',
                        'visit_date' => $request->visit_date->toDateString(),
                        'title' => $request->title ?? 'Tesis Ziyareti',
                        'notes' => $request->notes,
                        'created_at' => $request->created_at?->toIso8601String(),
                    ];
                })->values()
            : [];

        return Inertia::render('ZiyaretTakvimi', [
            'firms' => $firms,
            'consultants' => $consultants,
            'approvalRequests' => $approvalRequests,
            'holidays' => $this->calendar->blockedDates(now(BusinessCalendar::TIMEZONE)->year - 1, now(BusinessCalendar::TIMEZONE)->year + 3),
            'isAdmin' => $user->role === 'admin',
            'timezone' => BusinessCalendar::TIMEZONE,
        ]);
    }

    public function getEvents(Request $request)
    {
        $user = $request->user();
        $consultantFilter = $request->query('consultant', 'all');
        
        $query = Visit::with(['firm', 'user']);
        if ($user->role === 'admin') {
            $query->with('ministryEnteredBy:id,name');
        }

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        } elseif ($consultantFilter !== 'all') {
            $query->where('user_id', (int) $consultantFilter);
        }

        $visits = $query->get();

        $events = $visits->map(function($v) use ($user) {
            $firmName = ($v->firm && isset($v->firm->data['name'])) ? $v->firm->data['name'] : 'Bilinmeyen Tesis';
            $userName = $v->user ? $v->user->name : 'Bilinmiyor';
            $visitTitle = $v->title ?? 'Ziyaret';

            $event = [
                'id' => $v->id,
                'title' => $firmName . ' - ' . $visitTitle,
                'start' => $v->visit_date,
                'allDay' => true,
                'backgroundColor' => filled(trim((string) $v->notes)) ? '#d97706' : '#4f46e5',
                'borderColor' => filled(trim((string) $v->notes)) ? '#b45309' : '#4f46e5',
                'textColor' => '#ffffff',
                'classNames' => filled(trim((string) $v->notes)) ? ['visit-has-note'] : [],
                'extendedProps' => [
                    'user_id' => $v->user_id,
                    'firm_name' => $firmName,
                    'notes' => trim((string) $v->notes),
                    'consultant' => $userName
                ]
            ];

            if ($user->role === 'admin') {
                $snapshot = $v->ministry_transfer_snapshot ?? [];
                $currentSnapshot = [
                    'firm_id' => (string) $v->firm_id,
                    'user_id' => (int) $v->user_id,
                    'visit_date' => Carbon::parse($v->visit_date)->toDateString(),
                    'notes' => trim((string) $v->notes),
                ];
                $snapshotMatches = (string) ($snapshot['firm_id'] ?? '') === $currentSnapshot['firm_id']
                    && (int) ($snapshot['user_id'] ?? 0) === $currentSnapshot['user_id']
                    && ($snapshot['visit_date'] ?? '') === $currentSnapshot['visit_date']
                    && ($snapshot['notes'] ?? '') === $currentSnapshot['notes'];
                $transferStatus = $v->ministry_transfer_status ?? 'untracked';
                if ($transferStatus === 'entered' && !$snapshotMatches) {
                    $transferStatus = 'stale';
                }

                $event['borderColor'] = match ($transferStatus) {
                    'pending' => '#b91c1c',
                    'stale' => '#9a3412',
                    'entered' => '#15803d',
                    default => '#64748b',
                };
                $event['classNames'][] = 'visit-ministry-' . $transferStatus;
                $event['extendedProps']['ministry_transfer_status'] = $transferStatus;
                $event['extendedProps']['ministry_entered_at'] = $v->ministry_entered_at?->toIso8601String();
                $event['extendedProps']['ministry_entered_by'] = $v->ministryEnteredBy?->name;
            }

            return $event;
        });

        return response()->json(['events' => $events]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'firm_id' => 'required|exists:firms,id',
            'visit_date' => 'required|date_format:Y-m-d',
            'title' => 'nullable|string|max:255',
            'notes' => [$request->boolean('request_approval') ? 'required' : 'nullable', 'string', 'max:5000'],
            'request_approval' => 'sometimes|boolean',
            'admin_override' => 'sometimes|boolean',
            'user_id' => 'nullable|exists:users,id' // ID kontrolü
        ]);

        $user = $request->user();
        $today = Carbon::now(BusinessCalendar::TIMEZONE)->toDateString();
        if ($request->visit_date <= $today) {
            return response()->json(['error' => 'Ziyaret yalnızca yarın ve sonraki tarihler için planlanabilir.'], 422);
        }

        $firm = Firm::findOrFail($request->firm_id);
        $firmData = is_array($firm->data) ? $firm->data : json_decode($firm->data, true);
        
        $userId = ($user->role === 'admin' && $request->user_id) ? $request->user_id : $user->id;
        $targetUser = User::find($userId);
        $firmConsultant = $firm->consultantName();
        
        if (!$targetUser || (int) $firm->consultant_user_id !== (int) $targetUser->id) {
            $targetName = $targetUser?->name ?? 'seçilen kullanıcı';
            return response()->json(['error' => "UYARI: Seçtiğiniz tesis '{$firmConsultant}' danışmanına aittir. Ziyaret '{$targetName}' adına planlanamaz."], 422);
        }

        $dateString = $request->visit_date; 
        $date = Carbon::parse($dateString);

        if ($date->isWeekend()) {
            return response()->json(['error' => 'Hafta sonları (Cumartesi/Pazar) ziyaret planlanamaz.'], 422);
        }

        if ($this->calendar->isBlockedDate($dateString)) {
            return response()->json(['error' => 'Seçtiğiniz gün resmi tatildir, ziyaret planlanamaz.'], 422);
        }

        $existingVisit = Visit::with(['firm:id,data'])->where('user_id', $userId)->whereDate('visit_date', $dateString)->first();
        if ($existingVisit) {
            if ($request->boolean('request_approval')) {
                abort_if($user->role === 'admin', 403, 'Yönetici kendi hesabından onay isteği gönderemez.');
                $notes = trim((string) $request->input('notes'));
                if ($notes === '') {
                    return response()->json(['error' => 'Yöneticiye göndermeden önce açıklama notu yazın.'], 422);
                }

                $approval = DB::transaction(function () use ($user, $userId, $request, $dateString, $notes) {
                    User::query()->lockForUpdate()->findOrFail($userId);
                    $stillConflicted = Visit::where('user_id', $userId)->whereDate('visit_date', $dateString)->exists();
                    if (!$stillConflicted) return 'conflict_cleared';

                    $pending = VisitApprovalRequest::where('assigned_user_id', $userId)
                        ->whereDate('visit_date', $dateString)->where('status', 'pending')->exists();
                    if ($pending) return null;

                    return VisitApprovalRequest::create([
                        'firm_id' => (string) $request->firm_id,
                        'assigned_user_id' => $userId,
                        'requested_by_user_id' => $user->id,
                        'visit_date' => $dateString,
                        'title' => $request->title ?? 'Tesis Ziyareti',
                        'notes' => $notes,
                        'status' => 'pending',
                    ]);
                });

                if ($approval === 'conflict_cleared') {
                    return response()->json([
                        'conflict_cleared' => true,
                        'error' => 'Aynı gün çakışması artık bulunmuyor. Kaydet düğmesine yeniden basarak normal şekilde devam edin.',
                    ], 409);
                }

                if (!$approval) {
                    return response()->json(['error' => 'Bu danışman ve gün için zaten bekleyen bir yönetici onayı var.'], 409);
                }

                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'visit_approval_requested',
                    'description' => "Aynı gün ziyaret onayı istendi: {$firmData['name']} / Tarih: " . Carbon::parse($dateString)->format('d.m.Y') . " / Not: {$notes}",
                    'ip_address' => $request->ip(),
                ]);

                return response()->json(['ok' => true, 'approval_requested' => true], 202);
            }

            if (!($user->role === 'admin' && $request->boolean('admin_override'))) {
                $existingFirmName = ($existingVisit->firm && isset($existingVisit->firm->data['name'])) ? $existingVisit->firm->data['name'] : 'başka bir tesis';
                return response()->json([
                    'same_day_conflict' => true,
                    'error' => "Bu danışmanın {$date->format('d.m.Y')} tarihinde zaten bir ziyareti var: {$existingFirmName}.",
                ], 409);
            }
        }

        if ($request->boolean('request_approval')) {
            return response()->json(['error' => 'Aynı gün çakışması kalmadığı için yönetici onayı isteği gönderilemedi.'], 409);
        }

        $created = DB::transaction(function () use ($user, $userId, $request, $dateString, $firmData) {
            User::query()->lockForUpdate()->findOrFail($userId);
            $currentConflict = Visit::with(['firm:id,data'])->where('user_id', $userId)->whereDate('visit_date', $dateString)->first();
            $sameDayOverride = (bool) $currentConflict && $user->role === 'admin' && $request->boolean('admin_override');
            if ($currentConflict && !$sameDayOverride) return ['conflict' => $currentConflict];

            Visit::create([
                'firm_id' => $request->firm_id,
                'user_id' => $userId,
                'visit_date' => $dateString,
                'title' => $request->title ?? 'Tesis Ziyareti',
                'notes' => $request->notes,
                'ministry_transfer_status' => 'pending',
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => $sameDayOverride ? 'visit_same_day_override' : 'visit_created',
                'description' => ($sameDayOverride ? 'Yönetici onayıyla aynı güne istisnai ziyaret planlandı: ' : 'Ziyaret planlandı: ')
                    . ($firmData['name'] ?? 'İsimsiz tesis') . ' / Tarih: ' . Carbon::parse($dateString)->format('d.m.Y'),
                'ip_address' => $request->ip(),
            ]);

            return ['same_day_override' => $sameDayOverride];
        });

        if (isset($created['conflict'])) {
            $existingFirmName = $created['conflict']->firm?->data['name'] ?? 'başka bir tesis';
            return response()->json([
                'same_day_conflict' => true,
                'error' => "Bu danışmanın {$date->format('d.m.Y')} tarihinde zaten bir ziyareti var: {$existingFirmName}.",
            ], 409);
        }

        return response()->json(['ok' => true]);
    }

    public function decideApproval(Request $request, int $id, string $decision)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        $input = $request->validate(['decision_note' => 'nullable|string|max:1000']);

        $result = DB::transaction(function () use ($request, $id, $decision, $input) {
            $approval = VisitApprovalRequest::findOrFail($id);
            User::query()->lockForUpdate()->findOrFail($approval->assigned_user_id);
            $approval = VisitApprovalRequest::query()->lockForUpdate()->with('firm')->findOrFail($id);
            abort_unless($approval->status === 'pending', 409, 'Bu onay isteği daha önce sonuçlandırılmış.');

            if ($decision === 'reject') {
                $approval->update([
                    'status' => 'rejected', 'reviewed_by_user_id' => $request->user()->id,
                    'reviewed_at' => now(BusinessCalendar::TIMEZONE), 'decision_note' => $input['decision_note'] ?? null,
                ]);

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'visit_approval_rejected',
                    'description' => "Aynı gün ziyaret isteği reddedildi: " . ($approval->firm?->data['name'] ?? 'Bilinmeyen tesis')
                        . ' / Tarih: ' . $approval->visit_date->format('d.m.Y') . " / Danışman: {$approval->assignedUser?->name}"
                        . " / İstek notu: {$approval->notes} / Karar notu: " . ($input['decision_note'] ?? 'Belirtilmedi'),
                    'ip_address' => $request->ip(),
                ]);

                return ['status' => 'rejected'];
            }

            $date = Carbon::parse($approval->visit_date)->startOfDay();
            $today = Carbon::now(BusinessCalendar::TIMEZONE)->startOfDay();
            if ($date->lte($today) || $date->isWeekend() || $this->calendar->isBlockedDate($date->toDateString())) {
                $approval->update([
                    'status' => 'expired', 'reviewed_by_user_id' => $request->user()->id,
                    'reviewed_at' => now(BusinessCalendar::TIMEZONE), 'decision_note' => 'Onay anında ziyaret tarihi geçmiş veya planlamaya kapalıydı.',
                ]);

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'action' => 'visit_approval_expired',
                    'description' => "Aynı gün ziyaret isteği süresi dolduğu için onaylanamadı: " . ($approval->firm?->data['name'] ?? 'Bilinmeyen tesis')
                        . ' / Tarih: ' . $approval->visit_date->format('d.m.Y') . " / İstek notu: {$approval->notes}",
                    'ip_address' => $request->ip(),
                ]);

                return ['status' => 'expired'];
            }

            $firm = Firm::findOrFail($approval->firm_id);
            $firmData = is_array($firm->data) ? $firm->data : json_decode($firm->data, true);
            abort_unless((int) $firm->consultant_user_id === (int) $approval->assigned_user_id, 409, 'Tesis danışmanı değişmiş. İstek güncellenmeli.');

            $visit = Visit::create([
                'firm_id' => $approval->firm_id,
                'user_id' => $approval->assigned_user_id,
                'visit_date' => $approval->visit_date->toDateString(),
                'title' => $approval->title ?? 'Tesis Ziyareti',
                'notes' => $approval->notes,
                'ministry_transfer_status' => 'pending',
            ]);
            $approval->update([
                'status' => 'approved', 'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(BusinessCalendar::TIMEZONE), 'decision_note' => $input['decision_note'] ?? null,
                'visit_id' => $visit->id,
            ]);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'visit_approval_approved',
                'description' => "Aynı gün ziyaret isteği onaylandı: " . ($firmData['name'] ?? 'İsimsiz tesis') . ' / Tarih: ' . $approval->visit_date->format('d.m.Y') . " / Danışman: {$approval->assignedUser?->name} / İstek notu: {$approval->notes}",
                'ip_address' => $request->ip(),
            ]);

            return ['status' => 'approved', 'firm_name' => $firmData['name'] ?? 'İsimsiz tesis'];
        });

        if ($result['status'] === 'expired') {
            return response()->json(['ok' => false, 'status' => 'expired', 'error' => 'İstek sonuçlandırıldı; ziyaret tarihi geçmiş veya planlamaya kapalı olduğu için onaylanamadı.'], 422);
        }

        return response()->json(['ok' => true, 'status' => $result['status']]);
    }

    public function setMinistryTransfer(Request $request, int $id)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $validated = $request->validate(['transferred' => ['required', 'boolean']]);

        $visit = DB::transaction(function () use ($request, $id, $validated) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($id);
            $transferred = (bool) $validated['transferred'];

            $visit->ministry_transfer_status = $transferred ? 'entered' : 'pending';
            $visit->ministry_entered_at = $transferred ? now(BusinessCalendar::TIMEZONE) : null;
            $visit->ministry_entered_by_user_id = $transferred ? $request->user()->id : null;
            $visit->ministry_transfer_snapshot = $transferred ? [
                'firm_id' => (string) $visit->firm_id,
                'user_id' => (int) $visit->user_id,
                'visit_date' => Carbon::parse($visit->visit_date)->toDateString(),
                'notes' => trim((string) $visit->notes),
            ] : null;
            $visit->save();

            $firmName = $visit->firm?->data['name'] ?? 'Bilinmeyen tesis';
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => $transferred ? 'visit_ministry_transfer_marked' : 'visit_ministry_transfer_reset',
                'description' => ($transferred ? 'Bakanlık sistemine aktarıldı olarak işaretlendi: ' : 'Yeniden aktarım listesine alındı: ')
                    . $firmName . ' / Tarih: ' . Carbon::parse($visit->visit_date)->format('d.m.Y'),
                'ip_address' => $request->ip(),
            ]);

            return $visit->load('ministryEnteredBy:id,name');
        });

        return response()->json([
            'ok' => true,
            'status' => $visit->ministry_transfer_status,
            'ministry_entered_at' => $visit->ministry_entered_at?->toIso8601String(),
            'ministry_entered_by' => $visit->ministryEnteredBy?->name,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $user = $request->user();

        if ($user->role !== 'admin' && $visit->user_id !== $user->id) {
            return response()->json(['error' => 'Bu ziyareti silme yetkiniz yok.'], 403);
        }

        $visitDate = Carbon::parse($visit->visit_date)->toDateString();
        $today = Carbon::now(BusinessCalendar::TIMEZONE)->toDateString();

        if ($visitDate < $today) {
            return response()->json(['error' => 'Geçmiş tarihli ziyaretler silinemez.'], 422);
        }

        if ($visitDate === $today && Carbon::now(BusinessCalendar::TIMEZONE)->hour >= 16) {
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
