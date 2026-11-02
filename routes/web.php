<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

// GİRİŞ VE ÇIKIŞ ROTALARI
Route::get('/login', function () {
    return Inertia::render('auth/Login');
})->name('login')->middleware('guest');

Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/api/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
Route::post('/api/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// SADECE GİRİŞ YAPANLARIN ERİŞEBİLECEĞİ SAYFALAR VE API İŞLEMLERİ
Route::middleware(['auth'])->group(function () {
    
    // SAYFALAR (Inertia)
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tesisler', [FirmController::class, 'index'])->name('firms.index');
    Route::get('/firma/{id}', [FirmController::class, 'show'])->name('firms.show');
    Route::get('/izin-lisans', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('/emisyon', [EmissionController::class, 'index'])->name('emissions.index');
    Route::get('/ziyaret-takvimi', [VisitController::class, 'index'])->name('visits.index'); 
    
    // EXCEL, RAPOR VE LOG SAYFALARI
    Route::get('/excel-aktar', [ExcelController::class, 'index'])->name('excel.index'); 
    
    // GÖRSEL RAPORLAR (Grafikli Sayfa)
    Route::get('/raporlar', [ReportController::class, 'index'])->name('reports.index'); 
    
    // RAPOR İNDİR (Sadece İndirme Butonları Sayfası)
    Route::get('/rapor-indir', function () {
        return Inertia::render('RaporIndir');
    })->name('rapor-indir'); 
    
    Route::get('/islem-gecmisi', [LogController::class, 'index'])->name('logs.index');

    // SADECE ADMIN YETKİSİ GEREKTİREN SAYFALAR VE İŞLEMLER
    Route::middleware(['can:admin'])->group(function () {
        Route::get('/ayarlar', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/kullanicilar', [UserController::class, 'index'])->name('users.index');
        Route::get('/yedekler', [BackupController::class, 'index'])->name('backups.index');
        
        Route::post('/api/users', [UserController::class, 'store']);
        Route::put('/api/users/{id}', [UserController::class, 'update']);
        Route::post('/api/settings', [SettingsController::class, 'update']);
        Route::delete('/api/settings/obligation/{key}', [SettingsController::class, 'destroyObligation']);
        Route::post('/api/backups/run', [BackupController::class, 'run']);
        Route::get('/api/backups/download/{filename}', [BackupController::class, 'download']);
        Route::delete('/api/backups/{filename}', [BackupController::class, 'destroy']);
    });

    // DİĞER API İŞLEMLERİ
    Route::get('/api/visits/form-data', [VisitController::class, 'getFormData']);
    Route::get('/api/visits', [VisitController::class, 'getEvents']);
    Route::post('/api/visits', [VisitController::class, 'store']);
    Route::delete('/api/visits/{id}', [VisitController::class, 'destroy']);
    
    Route::get('/visit-data', [VisitController::class, 'getFormData']);
    Route::get('/visit-events', [VisitController::class, 'getEvents']);
    Route::post('/visit-events', [VisitController::class, 'store']);
    Route::delete('/visit-events/{id}', [VisitController::class, 'destroy']);

    Route::post('/api/firms', [FirmController::class, 'store']);
    Route::put('/api/firms/{id}', [FirmController::class, 'update']);
    Route::delete('/api/firms/{id}', [FirmController::class, 'destroy']);
    Route::post('/api/firms/{id}/archive', [FirmController::class, 'archive']);
    Route::get('/api/firms/{id}/logs', [FirmController::class, 'getLogs']);
    Route::post('/api/firms/{id}/note', [LicenseController::class, 'updateNote']);

    Route::post('/api/excel/upload-template', [ExcelController::class, 'uploadTemplate']);
    Route::post('/api/excel/preview', [ExcelController::class, 'preview']);
    Route::post('/api/excel/import', [ExcelController::class, 'import']);
    Route::post('/api/excel/destroy-all', [ExcelController::class, 'destroyAll']);
    Route::get('/api/download-backup/{filename}', [ExcelController::class, 'downloadBackup']);

    // RAPOR İNDİRME API
    Route::get('/api/export-excel', [ExportController::class, 'exportExcel']);
    Route::get('/api/export-pdf', [ReportController::class, 'exportPdf']);
});