<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Config;

class ComplianceEngine
{
    public const MONTHS_TR = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];

    // VARSAYILAN MEVZUATLAR (Kodun içinde kalacak olanlar)
    public const OBLIGATIONS = [
        // --- ŞİRKET BELGELERİ ---
        ['k' => 'kapasite', 'n' => 'Kapasite Raporu', 's' => 'Kapasite', 'g' => 'Şirket Belgeleri', 'kind' => 'periodic', 'p' => 730, 'red' => 60, 'amber' => 90],
        ['k' => 'police', 'n' => 'Atık Poliçesi (Sigorta)', 's' => 'Atık poliçe', 'g' => 'Şirket Belgeleri', 'kind' => 'periodic', 'p' => 365, 'red' => 30, 'amber' => 60],
        
        // --- İZİN VE KAPASİTE ---
        ['k' => 'izin', 'n' => 'Çevre İzin/Lisans', 's' => 'Çevre izin', 'g' => 'İzin ve kapasite', 'kind' => 'periodic', 'p' => 1825, 'red' => 60, 'amber' => 90, 
            'ms' => [['l' => 'İzin yenileme başvurusu', 'lead' => 180, 'red' => 30, 'amber' => 60]]],
        ['k' => 'ilUygunluk', 'n' => 'İl Uygunluk (başvuru / yenileme)', 's' => 'İl uygunluk', 'g' => 'İzin ve kapasite', 'kind' => 'linked_izin', 'lead' => 270, 
            'p' => null, 'red' => 60, 'amber' => 90, 'dlabel' => 'Uygunluk yazısı tarihi', 'snlabel' => 'Başvuru tarihi', 'tglabel' => 'Hedef / son başvuru tarihi',
            'labels' => ['tarih' => 'Uygunluk alındı (tarih)', 'surec' => 'Başvuru süreci açık', 'muaf' => 'Gerekmiyor / kapsam dışı'],
            'ask' => 'il uygunluk gerekli mi? başvuru yapıldı mı?',
            'help' => 'Yenileme son tarihi çevre izin bitişinden 270 gün önce otomatik hesaplanır. Yeni başvuru veya yenileme süreci açıksa “Başvuru süreci açık” seçin.'],
        
        // --- HAVA ---
        ['k' => 'emisyonOlcum', 'n' => 'Emisyon Ölçümü', 's' => 'Em. ölçüm', 'g' => 'Hava', 'kind' => 'periodic', 'p' => 720, 'red' => 60, 'amber' => 90],
        ['k' => 'emisyonRapor', 'n' => 'Emisyon Raporu', 's' => 'Em. rapor', 'g' => 'Hava', 'kind' => 'periodic', 'p' => 730, 'red' => 30, 'amber' => 60],
        
        // --- SU ---
        ['k' => 'atiksuIzleme', 'n' => 'Atıksu İç İzleme', 's' => 'İç izleme', 'g' => 'Su', 'kind' => 'periodic', 'p' => null, 'red' => 30, 'amber' => 60],
        ['k' => 'atiksuGorus', 'n' => 'Atıksu Görüşü', 's' => 'Atıksu görüşü', 'g' => 'Su', 'kind' => 'periodic', 'p' => null,
            'pBy' => ['KANALİZASYON İDARESİ' => 1825, 'OSB' => 1095], 'red' => 60, 'amber' => 90],
        
        // --- ATIK ---
        ['k' => 'aracLisans', 'n' => 'Araç Atık Taşıma Lisansı', 's' => 'Araç lisans', 'g' => 'Atık', 'kind' => 'periodic', 'p' => 1825, 'red' => 90, 'amber' => 180],
        ['k' => 'eayp', 'n' => 'EAYP (Atık Yönetim Planı)', 's' => 'EAYP', 'g' => 'Atık', 'kind' => 'periodic', 'p' => 1095, 'red' => 90, 'amber' => 120],
        ['k' => 'tayp', 'n' => 'Tıbbi Atık Yönetim Planı', 's' => 'Tıbbi atık YP', 'g' => 'Atık', 'kind' => 'periodic', 'p' => 365, 'red' => 90, 'amber' => 120],
        ['k' => 'sifirAtik', 'n' => 'Sıfır Atık Belgesi', 's' => 'Sıfır atık', 'g' => 'Atık', 'kind' => 'periodic', 'p' => 1825, 'red' => 60, 'amber' => 90],
        ['k' => 'madenAtik', 'n' => 'Maden Atık Yönetim Planı', 's' => 'Maden atık', 'g' => 'Atık', 'kind' => 'periodic', 'p' => 1825, 'red' => 90, 'amber' => 120],
        ['k' => 'kutleDenge', 'n' => 'Kütle Denge Bildirimi', 's' => 'Kütle denge', 'g' => 'Atık', 'kind' => 'monthly_lag', 'p' => 60, 'red' => 15, 'amber' => 30,
            'dlabel' => 'Son bildirilen ay', 'dtype' => 'month', 'help' => 'Aylık bildirilir; her ayın bildirimi ay sonundan itibaren en geç 60 gün içinde yapılabilir.'],
        
        // --- BİLDİRİM VE SİSTEMLER ---
        ['k' => 'bekra', 'n' => 'BEKRA', 's' => 'BEKRA', 'g' => 'Bildirim ve sistemler', 'kind' => 'periodic', 'p' => 365, 'red' => 30, 'amber' => 60],
        ['k' => 'ekomvet', 'n' => 'EKOMVET', 's' => 'EKOMVET', 'g' => 'Bildirim ve sistemler', 'kind' => 'periodic', 'p' => 365, 'red' => 60, 'amber' => 90],
        ['k' => 'kstk', 'n' => 'KSTK', 's' => 'KSTK', 'g' => 'Bildirim ve sistemler', 'kind' => 'annual_fixed', 'fm' => 4, 'fd' => 30, 'p' => null, 'red' => 30, 'amber' => 60,
            'dlabel' => 'Son beyan tarihi', 'help' => 'Yıllık beyan; her yıl Nisan ayı sonuna kadar yapılır. Kapsam dışıysa “Kapsam Dışı” seçin.'],
        ['k' => 'gekap', 'n' => 'GEKAP Beyanı', 's' => 'GEKAP', 'g' => 'Bildirim ve sistemler', 'kind' => 'periodic', 'p' => 90, 'red' => 30, 'amber' => 60],
        
        // --- SANAYİ DÖNÜŞÜMÜ ---
        ['k' => 'yesilDonusum', 'n' => 'Sanayide Yeşil Dönüşüm', 's' => 'Yeşil dönüşüm', 'g' => 'Sanayi dönüşümü', 'kind' => 'once', 'p' => null, 'red' => 30, 'amber' => 60,
            'dlabel' => 'Başvuru tarihi', 'snlabel' => 'Süreç başlangıcı', 'tglabel' => 'Son başvuru / hedef tarih', 'labels' => ['tarih' => 'Başvuru yapıldı (tarih)', 'surec' => 'Kapsamda, başvuru süreci açık', 'muaf' => 'Kapsam dışı'],
            'ask' => 'kapsamda mı? başvuru tarihi?', 'help' => 'Kapsam bilgisi ve başvuru tarihi. Son başvuru tarihi biliniyorsa “Kapsamda, başvuru süreci açık” seçip girin; kalan süre uyarı verir.'],
        ['k' => 'suVerimlilik', 'n' => 'Su Verimliliği', 's' => 'Su verimliliği', 'g' => 'Sanayi dönüşümü', 'kind' => 'once', 'p' => null, 'red' => 30, 'amber' => 60,
            'dlabel' => 'Başvuru tarihi', 'snlabel' => 'Süreç başlangıcı', 'tglabel' => 'Son başvuru / hedef tarih', 'labels' => ['tarih' => 'Başvuru yapıldı (tarih)', 'surec' => 'Kapsamda, başvuru süreci açık', 'muaf' => 'Kapsam dışı'],
            'ask' => 'kapsamda mı? başvuru tarihi?', 'help' => 'Kapsam bilgisi ve başvuru tarihi. Son başvuru tarihi biliniyorsa “Kapsamda, başvuru süreci açık” seçip girin; kalan süre uyarı verir.'],
        
        // --- YÖNETİM SİSTEMİ ---
        ['k' => 'icTetkik', 'n' => 'İç Tetkik', 's' => 'İç tetkik', 'g' => 'Yönetim sistemi', 'kind' => 'periodic', 'p' => 365, 'red' => 30, 'amber' => 60],
        ['k' => 'egitim', 'n' => 'Eğitim', 's' => 'Eğitim', 'g' => 'Yönetim sistemi', 'kind' => 'periodic', 'p' => 365, 'red' => 30, 'amber' => 60],
    ];

    public const RANK = ["overdue" => 0, "crit" => 1, "soon" => 2, "missing" => 3, "noperiod" => 4, "surec" => 5, "ok" => 6, "muaf" => 7];
    public const STATE_L = ["overdue" => "Gecikmiş", "crit" => "Kritik", "soon" => "Yaklaşan", "ok" => "Güncel", "surec" => "Süreçte", "missing" => "Veri yok", "noperiod" => "Periyot yok", "muaf" => "Kapsam Dışı"];
    public const DEFAULT_CFG = ['surecDays' => 90, 'staleDays' => 30, 'reviewDays' => 30, 'viewAll' => false, 'ob' => []];

    // YENİ: Tüm mevzuatları (Varsayılan + Veritabanından Gelenler) birleştirip döndürür
    public static function getAll(): array
    {
        $customObligationsRow = Config::find('custom_obligations');
        $customList = $customObligationsRow ? $customObligationsRow->v : [];
        
        if (!is_array($customList)) {
            $customList = [];
        }

        return array_merge(self::OBLIGATIONS, $customList);
    }

    public static function keys(): array
    {
        return array_column(self::getAll(), 'k');
    }

    public static function obl(string $k): ?array
    {
        foreach (self::getAll() as $o) {
            if ($o['k'] === $k) return $o;
        }
        return null;
    }

    public static function pdate(?string $s): ?Carbon
    {
        if (is_string($s)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                try { return Carbon::createFromFormat('Y-m-d', $s)->startOfDay(); } catch (\Exception $e) { return null; }
            }
            if (preg_match('/^\d{4}-\d{2}$/', $s)) {
                try { return Carbon::createFromFormat('Y-m', $s)->startOfDay(); } catch (\Exception $e) { return null; }
            }
        }
        return null;
    }

    public static function rule(array $cfg, string $k): array
    {
        $d = self::obl($k);
        $o = $cfg['ob'][$k] ?? [];
        $out = [];
        foreach (['p', 'red', 'amber', 'fm', 'fd'] as $x) {
            $out[$x] = $d[$x] ?? null;
            if (isset($o[$x])) $out[$x] = $o[$x];
        }
        return $out;
    }

    private static function st(int $days, int $red, int $amber): string
    {
        return $days < 0 ? 'overdue' : ($days <= $red ? 'crit' : ($days <= $amber ? 'soon' : 'ok'));
    }

    private static function base(string $k, array $it): array
    {
        return ['k' => $k, 'state' => 'missing', 'days' => null, 'due' => null, 'label' => '', 'flags' => [], 'tasks' => [], 'expiry' => null,
                'kind' => null, 'openDays' => null, 'lastUpdate' => $it['u'] ?? null, 'lastUpdateBy' => $it['ub'] ?? null];
    }

    public static function evaluate(array $firm, string $k, Carbon $today, array $cfg): array
    {
        $d = self::obl($k);
        $it = $firm['items'][$k] ?? [];
        $r = self::rule($cfg, $k);
        $out = self::base($k, $it);
        $s = $it['s'] ?? null;
        
        if ($s === 'muaf') { $out['state'] = 'muaf'; return $out; }
        if ($s === 'surec') { return self::evalSurec($it, $out, $today, $cfg); }

        $kind = $d['kind'];
        if ($kind === 'linked_izin') { return self::evalLinkedIzin($firm, $d, $it, $out, $today, $cfg); }
        if ($s !== 'tarih') { return $out; }

        $last = self::pdate($it['d'] ?? null);
        if (!$last) { $out['flags'][] = "Tarih okunamadı"; return $out; }
        if ($last->greaterThan($today)) { $out['flags'][] = "Son yapılan tarih gelecekte"; }

        if ($kind === 'once') {
            $out['state'] = 'ok'; $out['label'] = 'Başvuru yapıldı'; return $out;
        }

        if ($kind === 'annual_fixed') {
            $fm = $r['fm'] ?? 4; $fd = $r['fd'] ?? 30;
            $dl = Carbon::create($today->year, $fm, 1)->endOfMonth();
            if ($last->year === $today->year) {
                $nxt = Carbon::create($today->year + 1, $fm, $fd);
                if ($last->greaterThan($dl)) { $out['flags'][] = "Beyan {$fd} ".self::MONTHS_TR[$fm-1]." süresinden sonra yapılmış"; }
            } else {
                $nxt = Carbon::create($today->year, $fm, $fd);
            }
            $days = (int) $today->diffInDays($nxt, false); 
            $out = array_merge($out, ['due' => $nxt->toDateString(), 'days' => $days, 'state' => self::st($days, $r['red'], $r['amber']), 'label' => "Yıllık beyan son günü ({$fd} ".self::MONTHS_TR[$fm-1].")"]);
            $out['tasks'] = [['l' => $out['label'], 'due' => $out['due'], 'days' => $days, 'state' => $out['state']]];
            return $out;
        }

        if ($kind === 'monthly_lag') {
            $lag = $r['p'] ?? 60;
            $ny = $last->month === 12 ? $last->year + 1 : $last->year;
            $nm = $last->month === 12 ? 1 : $last->month + 1;
            
            $due = Carbon::create($ny, $nm, 1)->endOfMonth()->addDays($lag)->startOfDay();
            $days = (int) $today->diffInDays($due, false); 
            
            $late = 0;
            $checkDate = $due->copy();
            while ($checkDate->lessThan($today) && $late < 120) {
                $late++;
                $checkDate->addMonth()->endOfMonth()->addDays($lag);
            }
            $label = self::MONTHS_TR[$nm-1]." {$ny} dönemi bildirimi";
            $out = array_merge($out, ['due' => $due->toDateString(), 'days' => $days, 'state' => self::st($days, $r['red'], $r['amber']), 'label' => $label]);
            if ($late >= 1) $out['flags'][] = "{$late} aylık bildirim gecikmiş";
            $out['tasks'] = [['l' => $label, 'due' => $out['due'], 'days' => $days, 'state' => $out['state']]];
            return $out;
        }

        $p = null;
        if (!empty($d['pBy'])) {
            $p = $d['pBy'][$firm['profile']['atiksuKurum'] ?? ''] ?? null;
        } else {
            $p = $r['p'];
        }
        
        if (!$p) {
            $out['state'] = 'noperiod';
            $out['label'] = $k === 'atiksuGorus' ? 'Atıksu kurumu/periyodu belirsiz' : 'Periyot tanımlı değil';
            return $out;
        }
        
        $defs = [];
        if (!empty($d['ms'])) {
            foreach ($d['ms'] as $m) {
                $defs[] = ['l' => $m['l'], 'due' => $last->copy()->addDays($p - $m['lead']), 'red' => $m['red'], 'amber' => $m['amber']];
            }
        } else {
            $defs[] = ['l' => $d['n'], 'due' => $last->copy()->addDays($p), 'red' => $r['red'], 'amber' => $r['amber']];
        }

        $tasks = [];
        foreach ($defs as $t) {
            $days = (int) $today->diffInDays($t['due'], false); 
            $tasks[] = ['l' => $t['l'], 'due' => $t['due']->toDateString(), 'days' => $days, 'state' => self::st($days, $t['red'], $t['amber'])];
        }
        
        usort($tasks, fn($a, $b) => [self::RANK[$a['state']], $a['days']] <=> [self::RANK[$b['state']], $b['days']]);
        $w = $tasks[0];
        $out = array_merge($out, ['state' => $w['state'], 'days' => $w['days'], 'due' => $w['due'], 'label' => $w['l'], 'tasks' => $tasks]);
        
        if (!empty($d['ms'])) $out['expiry'] = $last->copy()->addDays($p)->toDateString();
        return $out;
    }

    private static function evalLinkedIzin(array $firm, array $d, array $it, array $out, Carbon $today, array $cfg): array
    {
        $iz = $firm['items']['izin'] ?? [];
        $izd = ($iz['s'] ?? null) === 'tarih' ? self::pdate($iz['d'] ?? null) : null;
        
        if (($it['s'] ?? null) === 'surec') return self::evalSurec($it, $out, $today, $cfg);

        if ($izd) {
            $p = self::rule($cfg, 'izin')['p'] ?? 1825;
            $r = self::rule($cfg, 'ilUygunluk');
            $due = $izd->copy()->addDays($p - $d['lead']);
            $days = (int) $today->diffInDays($due, false); 
            $label = "İl uygunluk yenileme başvurusu (izin bitişinden {$d['lead']} gün önce)";
            $out = array_merge($out, ['due' => $due->toDateString(), 'days' => $days, 'state' => self::st($days, $r['red'], $r['amber']), 'label' => $label, 'expiry' => $izd->copy()->addDays($p)->toDateString()]);
            $out['tasks'] = [['l' => $label, 'due' => $out['due'], 'days' => $days, 'state' => $out['state']]];
            return $out;
        }
        if (($it['s'] ?? null) === 'tarih') {
            $out['state'] = 'noperiod';
            $out['label'] = 'Çevre izin tarihine bağlı; izin tarihi girilmemiş';
        }
        return $out;
    }

    private static function evalSurec(array $it, array $out, Carbon $today, array $cfg): array
    {
        $since = self::pdate($it['since'] ?? null);
        $target = self::pdate($it['target'] ?? null);
        $out['state'] = 'surec'; $out['kind'] = 'surec';
        
        if (!empty($it['wait'])) $out['wait'] = $it['wait'];
        if ($since) $out['openDays'] = (int) $today->diffInDays($since, false); 
        
        if ($target) {
            $out['due'] = $target->toDateString();
            $out['days'] = (int) $today->diffInDays($target, false); 
            if ($out['days'] < 0) {
                $out['state'] = 'overdue'; $out['label'] = 'Süreç hedef tarihi geçti';
            } elseif ($out['days'] <= 14) {
                $out['state'] = 'crit'; $out['label'] = 'Süreç hedef tarihine az kaldı';
            } elseif ($out['days'] <= 30) {
                $out['state'] = 'soon'; $out['label'] = 'Süreç hedef tarihi yaklaşıyor';
            }
        } else {
            $out['flags'][] = 'Hedef tarih girilmemiş';
        }
        
        if (!$since) $out['flags'][] = 'Süreç başlangıç tarihi girilmemiş';
        
        if (!$since && !$target) {
            $out['state'] = 'overdue'; $out['label'] = 'Süreçte; başlangıç ve hedef tarihi yok (gecikmiş sayılır)';
        } elseif ($since && $out['openDays'] > $cfg['surecDays'] && $out['state'] !== 'overdue') {
            $out['state'] = 'overdue'; $out['label'] = "Süreç {$out['openDays']} gündür açık";
        }
        
        $u = $it['u'] ?? null;
        if ($u) {
            try {
                $ud = Carbon::parse(substr($u, 0, 10));
                $age = (int) $today->diffInDays($ud, false); 
                if ($age > $cfg['staleDays']) {
                    $out['flags'][] = "Durum {$age} gündür güncellenmedi";
                    if (self::RANK[$out['state']] > self::RANK['crit']) {
                        $out['state'] = 'crit';
                        $out['label'] = $out['label'] ?: 'Süreç durumu güncel değil';
                    }
                }
            } catch (\Exception $e) {}
        }
        return $out;
    }

    public static function evaluateFirm(array $firm, Carbon $today, array $cfg): array
    {
        $ev = [];
        foreach (self::keys() as $k) {
            $ev[$k] = self::evaluate($firm, $k, $today, $cfg);
        }
        return $ev;
    }

    public static function issuesOf(array $firm, array $ev, Carbon $today, array $cfg): array
    {
        $out = [];
        $items = $firm['items'] ?? [];
        $p = $firm['profile'] ?? [];

        foreach (self::keys() as $k) {
            $e = $ev[$k]; $n = self::obl($k)['n']; $it = $items[$k] ?? [];
            
            if ($e['state'] === 'missing') {
                if (empty($it['s'])) {
                    $ask = $k === 'kstk' ? 'kapsamda mı? Nisan beyanı yapıldı mı?' : (self::obl($k)['ask'] ?? 'kapsam dışı mı, süreçte mi, tarih mi?');
                    $out[] = ['k' => $k, 't' => "{$n}: durum girilmemiş ({$ask})"];
                } else {
                    $out[] = ['k' => $k, 't' => "{$n}: ".(($e['flags'][0] ?? null) ?: 'tarih eksik')];
                }
            } elseif ($e['state'] === 'noperiod') {
                $out[] = ['k' => $k, 't' => "{$n}: {$e['label']}; son tarih hesaplanamıyor"];
            }

            if (($it['s'] ?? null) === 'surec') {
                foreach ($e['flags'] as $f) {
                    if (strpos($f, 'güncellenmedi') === false) $out[] = ['k' => $k, 't' => "{$n}: {$f}"];
                }
            }
            if (($it['s'] ?? null) === 'tarih' && in_array('Son yapılan tarih gelecekte', $e['flags'])) {
                $out[] = ['k' => $k, 't' => "{$n}: son yapılan tarih gelecekte, yazım hatası olabilir"];
            }
        }

        $ep = $items['emisyonOlcum'] ?? []; $er = $items['emisyonRapor'] ?? [];
        if (($ep['s'] ?? null) === 'tarih' && ($er['s'] ?? null) === 'tarih' && self::pdate($ep['d'] ?? null) && self::pdate($er['d'] ?? null) && self::pdate($er['d'])->lessThan(self::pdate($ep['d']))) {
            $out[] = ['k' => 'emisyonRapor', 't' => 'Emisyon raporu tarihi, ölçüm tarihinden eski'];
        }

        if (empty($p['atiksuTipi'])) $out[] = ['k' => '_p', 't' => 'Atıksu tipi girilmemiş'];
        if (empty($p['atiksuKurum'])) $out[] = ['k' => '_p', 't' => 'Atıksu görüş kurumu girilmemiş'];
        if (empty($p['cedKarari'])) {
            $out[] = ['k' => '_p', 't' => 'ÇED kararı girilmemiş'];
        } elseif ($p['cedKarari'] !== 'SÜREÇTE' && empty($p['cedTarihi'])) {
            $out[] = ['k' => '_p', 't' => 'ÇED görüş tarihi girilmemiş'];
        }

        $ik = $p['izinKonusu'] ?? '';
        if (!$ik) $out[] = ['k' => '_p', 't' => 'Çevre izin konusu girilmemiş'];
        $iz = $items['izin']['s'] ?? null;
        
        if ($ik && strpos($ik, 'İZİN') !== false && strpos($ik, 'KAPSAMDIŞI') === false && $iz === 'muaf') {
            $out[] = ['k' => 'izin', 't' => "İzin konusu “{$ik}” ama izin durumu kapsam dışı; hangisi doğru?"];
        }
        if (strpos($ik, 'KAPSAMDIŞI') !== false && $iz === 'tarih') {
            $out[] = ['k' => 'izin', 't' => "İzin konusu “{$ik}” ama izin tarihi girilmiş"];
        }
        if (strpos($ik, 'KAPSAMDIŞI') !== false && !$iz) {
            $out[] = ['k' => 'izin', 't' => 'İzin konusu kapsam dışı, izin durumu boş'];
        }
        if (empty($firm['consultant'])) $out[] = ['k' => '_p', 't' => 'Danışman atanmamış'];
        
        return $out;
    }

    public static function taskList(array $firms, array $evs, bool $includeInactive = false): array
    {
        $rows = [];
        foreach ($firms as $f) {
            if (($f['status'] ?? null) === 'askida' && !$includeInactive) continue;
            foreach (self::keys() as $k) {
                $e = $evs[$f['id']][$k];
                if (in_array($e['state'], ['ok', 'muaf'])) continue;
                $rows[] = [$f, $k, $e];
            }
        }
        
        usort($rows, function($a, $b) {
            $rankA = self::RANK[$a[2]['state']]; $rankB = self::RANK[$b[2]['state']];
            if ($rankA !== $rankB) return $rankA <=> $rankB;
            $daysA = $a[2]['days'] ?? 10**9; $daysB = $b[2]['days'] ?? 10**9;
            if ($daysA !== $daysB) return $daysA <=> $daysB;
            return strcmp($a[0]['name'], $b[0]['name']);
        });
        
        return $rows;
    }
}