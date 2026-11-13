<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Middleware\EnsureEnvironmentModuleSelected;
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
                'available' => $module->is_active && $module->key === 'environment',
                'href' => $module->key === 'environment' && $module->is_active ? '/moduller/cevre-takip' : null,
            ])->values(),
        ]);
    })->name('modules.index');
    Route::post('/moduller/cevre-takip', function (Request $request) {
        $request->session()->put('active_module', 'environment');

        return redirect()->route('dashboard');
    })->middleware('module.access:environment')->name('modules.select.environment');

    Route::middleware([EnsureEnvironmentModuleSelected::class, 'module.access:environment'])->group(function () {
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
