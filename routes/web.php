<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Middleware\EnsureEnvironmentModuleSelected;
use App\Http\Middleware\EnsureWasteModuleSelected;
use App\Models\AppModule;

// CONTROLLER SINIFLARI
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmissionController;
use App\Http\Controllers\Api\ExcelController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FirmController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\CalendarHolidayController;

// GİRİŞ VE ÇIKIŞ ROTALARI
Route::get('/login', function () {
    return Inertia::render('auth/Login');
})->name('login')->middleware('guest');

Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/api/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
Route::post('/api/logout', [AuthController::class, 'logout'])->name('api.logout')->middleware('auth');

// SADECE GİRİŞ YAPANLARIN ERİŞEBİLECEĞİ SAYFALAR VE API İŞLEMLERİ
Route::middleware(['auth', 'session.current'])->group(function () {
    Route::get('/sifre-degistir', function () {
        return Inertia::render('auth/ChangePassword');
    })->name('password.change');
    Route::post('/api/sifre-degistir', [AuthController::class, 'changePassword'])->name('password.change.store');

    Route::middleware(['password.changed', 'admin'])->group(function () {
        Route::get('/yonetim/kullanicilar', [UserController::class, 'index'])->name('platform.users.index');
        Route::redirect('/kullanicilar', '/yonetim/kullanicilar')->name('users.index');
        Route::post('/api/users', [UserController::class, 'store']);
        Route::put('/api/users/{id}', [UserController::class, 'update']);
        Route::post('/api/users/{id}/consultant-handover', [UserController::class, 'handoverConsultant']);
    });

    Route::middleware(['password.changed'])->group(function () {
    
    // SAYFALAR (Inertia)
    Route::get('/moduller', function (Request $request) {
        $request->session()->forget('active_module');

        $query = AppModule::query()->orderBy('sort_order');
        if ($request->user()->role !== 'admin') {
            $query->whereIn('id', $request->user()->moduleAccesses()->select('module_id'));
        }

        return Inertia::render('ModulSecimi', [
            'modules' => $query->get()->map(fn (AppModule $module) => [
                'name' => $module->name,
                'description' => $module->description,
                'available' => $module->is_active && in_array($module->key, ['environment', 'waste'], true),
        'href' => $module->is_active ? match ($module->key) {
                    'environment' => '/moduller/cevre-takip',
                    'waste' => '/moduller/atik-takip',
                    default => null,
                } : null,
            ])->values(),
        ]);
    })->name('modules.index');
    Route::post('/moduller/cevre-takip', function (Request $request) {
        $request->session()->put('active_module', 'environment');

        return redirect()->route('dashboard');
    })->middleware('module.access:environment')->name('modules.select.environment');

    Route::post('/moduller/atik-takip', function (Request $request) {
        abort_unless(AppModule::where('key', 'waste')->where('is_active', true)->exists(), 404);
        $request->session()->put('active_module', 'waste');

        $moduleRole = $request->user()->moduleAccesses()
            ->whereHas('module', fn ($query) => $query->where('key', 'waste'))
            ->value('role');
        return redirect()->route($moduleRole === AppModule::ROLE_VIEWER ? 'waste.statistics' : 'waste.dashboard');
    })->middleware('module.access:waste')->name('modules.select.waste');

    Route::middleware([EnsureWasteModuleSelected::class, 'module.access:waste', 'waste.statistics-only'])->group(function () {
        Route::get('/atik-takip/yedek', [\App\Http\Controllers\Api\WasteBackupController::class, 'download'])->middleware('admin')->name('waste.backup');
        Route::post('/atik-takip/ice-aktar/onizleme', [\App\Http\Controllers\Api\WasteImportController::class, 'preview'])->middleware('admin');
        Route::post('/atik-takip/ice-aktar/onay', [\App\Http\Controllers\Api\WasteImportController::class, 'commit'])->middleware('admin');
        Route::put('/atik-takip/tesis-karti', [\App\Http\Controllers\Api\WasteTransportController::class, 'saveFacilityCard'])->middleware('admin');
        Route::get('/atik-takip', function () {
            $today = now('Europe/Istanbul')->startOfDay();
            $weekStart = $today->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
            $monthStart = $today->copy()->startOfMonth()->toDateString();
            $monthEnd = $today->copy()->endOfMonth()->toDateString();
            $week = DB::table('waste_imports')->whereDate('waste_date', '>=', $weekStart->toDateString())
                ->whereDate('waste_date', '<=', $weekEnd)
                ->where(fn ($query) => $query->whereNull('transport_status')->orWhere('transport_status', '!=', 'cancelled'));
            $weekRecords = (clone $week)->select(['waste_date', 'producer_name', 'processor_name', 'waste_code'])
                ->orderBy('waste_date')->get()
                ->groupBy(fn ($record) => substr((string) $record->waste_date, 0, 10))
                ->map(function ($records, $date) {
                    $companies = $records->pluck('producer_name')->merge($records->pluck('processor_name'))
                        ->filter()->map(fn ($name) => trim((string) $name))->filter()->unique()->values();

                    return [
                        'waste_date' => $date,
                        'transport_count' => $records->count(),
                        'company_count' => $companies->count(),
                        'waste_type_count' => $records->pluck('waste_code')->filter()->unique()->count(),
                        'companies' => $companies,
                    ];
                })->values();
            return Inertia::render('AtikTakipDashboard', [
                'weekCount' => (clone $week)->count(),
                'weekRecords' => $weekRecords,
                'weekUrl' => '/atik-takip/kayitlar?from='.$weekStart->toDateString().'&to='.$weekEnd->toDateString(),
                'monthUrl' => '/atik-takip/kayitlar?from='.$monthStart.'&to='.$monthEnd,
            ]);
        })->name('waste.dashboard');
        Route::get('/atik-takip/kayitlar', [\App\Http\Controllers\Api\WasteTransportController::class, 'index'])->name('waste.records');
        Route::get('/atik-takip/kayitlar/excel', [\App\Http\Controllers\Api\WasteTransportController::class, 'exportExcel'])->name('waste.records.excel');
        Route::patch('/atik-takip/kayitlar/{id}/adr', [\App\Http\Controllers\Api\WasteTransportController::class, 'classifyAdr'])->whereNumber('id');
        Route::patch('/atik-takip/adr-yetki', [\App\Http\Controllers\Api\WasteTransportController::class, 'updateAdrAccess'])->middleware('admin');
        Route::post('/atik-takip/kayitlar', [\App\Http\Controllers\Api\WasteTransportController::class, 'save'])->middleware('module.write:waste');
        Route::put('/atik-takip/kayitlar/{id}', [\App\Http\Controllers\Api\WasteTransportController::class, 'save'])->whereNumber('id')->middleware('module.write:waste');
        Route::patch('/atik-takip/kayitlar/{id}/gerceklesmedi', [\App\Http\Controllers\Api\WasteTransportController::class, 'cancel'])->whereNumber('id')->middleware('admin');
        Route::get('/atik-takip/ureticiler', [\App\Http\Controllers\Api\WasteTransportController::class, 'index'])->defaults('facilityView', 'producer')->name('waste.producers');
        Route::get('/atik-takip/isleme-tesisleri', [\App\Http\Controllers\Api\WasteTransportController::class, 'index'])->defaults('facilityView', 'processor')->name('waste.processors');
        Route::get('/atik-takip/istatistikler', [\App\Http\Controllers\Api\WasteTransportController::class, 'index'])->middleware('waste.statistics-access')->defaults('statisticsView', true)->name('waste.statistics');
    });

    Route::middleware([EnsureEnvironmentModuleSelected::class, 'module.access:environment'])->group(function () {
    Route::get('/duyurular', [\App\Http\Controllers\Api\AnnouncementController::class, 'index']);
    Route::post('/duyurular/{announcement}/okudum', [\App\Http\Controllers\Api\AnnouncementController::class, 'acknowledge'])
        ->where('announcement', '[a-z0-9-]{1,100}');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tesisler', [FirmController::class, 'index'])->name('firms.index');
    Route::get('/firma/{id}', [FirmController::class, 'show'])->name('firms.show');
    Route::get('/izin-lisans', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('/emisyon', [EmissionController::class, 'index'])->name('emissions.index');
    Route::get('/ziyaret-takvimi', [VisitController::class, 'index'])->name('visits.index'); 
    
    // EXCEL, RAPOR VE LOG SAYFALARI
    Route::get('/excel-aktar', [ExcelController::class, 'index'])->name('excel.index'); 
    Route::get('/raporlar', [ReportController::class, 'index'])->name('reports.index'); 
    Route::redirect('/rapor-indir', '/raporlar');
    Route::get('/islem-gecmisi', [LogController::class, 'index'])->name('logs.index');

    // SADECE ADMIN YETKİSİ GEREKTİREN SAYFALAR VE İŞLEMLER
    Route::middleware(['admin'])->group(function () { // BURASI 'admin' OLARAK DEĞİŞTİRİLDİ
        Route::get('/ayarlar', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/danisman-takibi', [UserController::class, 'consultantOverview'])->name('consultants.overview');
        Route::get('/yedekler', [BackupController::class, 'index'])->name('backups.index');
        Route::get('/takvim-ayarlari', [CalendarHolidayController::class, 'index'])->name('calendar.settings.index');
        
        Route::post('/api/settings', [SettingsController::class, 'update']);
        Route::delete('/api/settings/obligation/{key}', [SettingsController::class, 'destroyObligation']);
        Route::post('/api/backups/run', [BackupController::class, 'run']);
        Route::get('/api/backups/download/{filename}', [BackupController::class, 'download']);
        Route::delete('/api/backups/{filename}', [BackupController::class, 'destroy']);
        Route::get('/api/calendar-holidays', [CalendarHolidayController::class, 'list']);
        Route::post('/api/calendar-holidays', [CalendarHolidayController::class, 'store']);
        Route::delete('/api/calendar-holidays/{calendarHoliday}', [CalendarHolidayController::class, 'destroy']);
        Route::post('/api/excel/upload-template', [ExcelController::class, 'uploadTemplate']);
    });

    // DİĞER API İŞLEMLERİ
    Route::get('/api/visits/form-data', [VisitController::class, 'getFormData']);
    Route::get('/api/visits', [VisitController::class, 'getEvents']);
    Route::post('/api/visits', [VisitController::class, 'store'])->middleware('module.write:environment');
    Route::delete('/api/visits/{id}', [VisitController::class, 'destroy'])->middleware('module.write:environment');
    
    Route::get('/visit-data', [VisitController::class, 'getFormData']);
    Route::get('/visit-events', [VisitController::class, 'getEvents']);
    Route::post('/visit-events', [VisitController::class, 'store'])->middleware('module.write:environment');
    Route::delete('/visit-events/{id}', [VisitController::class, 'destroy'])->middleware('module.write:environment');

    Route::post('/api/firms', [FirmController::class, 'store'])->middleware('module.write:environment');
        Route::put('/api/firms/{id}', [FirmController::class, 'update'])->middleware('module.write:environment');
        Route::put('/api/firms/{id}/engagement', [FirmController::class, 'updateEngagement'])->middleware('module.write:environment');
        Route::put('/api/firms/{id}/wastewater-verification', [FirmController::class, 'updateWastewaterVerification'])->middleware('module.write:environment');
    Route::delete('/api/firms/{id}', [FirmController::class, 'destroy'])->middleware('module.write:environment');
    Route::post('/api/firms/{id}/archive', [FirmController::class, 'archive'])->middleware('module.write:environment');
    Route::get('/api/firms/{id}/logs', [FirmController::class, 'getLogs']);
    Route::post('/api/excel/preview', [ExcelController::class, 'preview']);
    Route::post('/api/excel/import', [ExcelController::class, 'import'])->middleware('module.write:environment');

    Route::get('/api/export-excel', [ExportController::class, 'exportExcel']);
    Route::get('/api/export-pdf', [ReportController::class, 'exportPdf']);
    });
    });
});
