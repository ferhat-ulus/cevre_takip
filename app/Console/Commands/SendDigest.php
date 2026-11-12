<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Firm;
use App\Models\Config;
use App\Services\ComplianceEngine;
use App\Mail\DigestMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendDigest extends Command
{
    // Komutun adı (php artisan app:send-digest)
    protected $signature = 'app:send-digest {--test= : Test için tek bir e-posta adresine gönder}';
    protected $description = 'Gecikmiş, kritik ve yaklaşan işlerin e-posta özetini gönderir.';

    public function handle()
    {
        $today = Carbon::today();
        $cfgRow = Config::find('main');
        $cfg = array_merge(ComplianceEngine::DEFAULT_CFG, $cfgRow ? $cfgRow->v : []);
        
        $firmsRaw = Firm::with('consultant:id,name')->get();
        $firms = [];
        foreach ($firmsRaw as $f) {
            $data = $f->data;
            $data['id'] = $f->id;
            $data['consultant'] = $f->consultantName();
            $data['consultant_user_id'] = $f->consultant_user_id;
            $firms[] = $data;
        }

        // Test modu kontrolü
        $testEmail = $this->option('test');
        $users = $testEmail 
            ? collect([(object)['name' => 'Test Kullanıcısı', 'email' => $testEmail, 'role' => 'admin']]) 
            : User::where('active', 1)->where('email', '!=', '')->get();

        if ($users->isEmpty()) {
            $this->info('Gönderilecek kullanıcı bulunamadı (E-posta adresleri boş olabilir).');
            return;
        }

        $sentCount = 0;

        foreach ($users as $user) {
            // Kullanıcının yetkisine göre firmaları filtrele
            $userFirms = [];
            if ($user->role === 'admin') {
                $userFirms = $firms;
            } else {
                $userFirms = array_filter($firms, fn($f) => (int) ($f['consultant_user_id'] ?? 0) === (int) $user->id);
            }

            if (count($userFirms) === 0) continue;

            $evs = [];
            foreach ($userFirms as $f) {
                $evs[$f['id']] = ComplianceEngine::evaluateFirm($f, $today, $cfg);
            }

            // Açık işleri al
            $tasks = ComplianceEngine::taskList($userFirms, $evs);
            
            // Sadece gecikmiş, kritik, yaklaşan ve eksik verileri filtrele
            $filteredTasks = array_filter($tasks, function($t) {
                return in_array($t[2]['state'], ['overdue', 'crit', 'soon', 'missing', 'noperiod']);
            });

            // Eğer açık iş yoksa mail atma
            if (count($filteredTasks) === 0) continue;

            // İstatistikleri hesapla
            $stats = ['overdue' => 0, 'crit' => 0, 'soon' => 0];
            foreach ($filteredTasks as $t) {
                if (in_array($t[2]['state'], ['missing', 'overdue'], true)) $stats['overdue']++;
                if ($t[2]['state'] === 'crit') $stats['crit']++;
                if ($t[2]['state'] === 'soon') $stats['soon']++;
            }

            // Mail Gönder
            try {
                Mail::to($user->email)->send(new DigestMail($user, $filteredTasks, $stats));
                $sentCount++;
                $this->info("E-posta gönderildi: {$user->name} ({$user->email})");
            } catch (\Exception $e) {
                $this->error("E-posta gönderilemedi: {$user->email} - Hata: " . $e->getMessage());
            }
        }

        $this->info("İşlem tamamlandı. Toplam {$sentCount} e-posta gönderildi.");
    }
}
