<!DOCTYPE html>
<html lang="tr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Çevre Mevzuat Takip Raporu</title>
    <style>
        /* Temel Ayarlar */
        @page { margin: 30px 30px 50px 30px; }
        body { 
            font-family: 'DejaVu Sans', sans-serif; 
            font-size: 10px; 
            color: #333333; 
            margin: 0; 
            padding: 0; 
            background-color: #ffffff;
        }
        
        /* Başlık Bloğu */
        .header-block {
            background-color: #4f46e5;
            color: #ffffff;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .header-block h1 { margin: 0; font-size: 18px; font-weight: bold; }
        .header-block p { margin: 5px 0 0 0; font-size: 10px; color: #c7d2fe; }
        
        /* Bölüm Başlıkları */
        .section-title { 
            font-size: 12px; 
            font-weight: bold; 
            color: #1e293b; 
            background-color: #f1f5f9;
            padding: 8px 12px;
            border-left: 4px solid #4f46e5;
            margin: 20px 0 10px 0;
        }

        /* KPI Kartları (Özet Tablo) */
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-left: -8px; margin-bottom: 15px; }
        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
            border-top: 4px solid #cbd5e1;
        }
        .kpi-card.danger { border-top-color: #e11d48; }
        .kpi-card.warning { border-top-color: #ea580c; }
        .kpi-card.soon { border-top-color: #d97706; }
        .kpi-card.success { border-top-color: #059669; }
        
        .kpi-label { font-size: 9px; font-weight: bold; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .kpi-value { font-size: 18px; font-weight: bold; }
        .kpi-card.danger .kpi-value { color: #e11d48; }
        .kpi-card.warning .kpi-value { color: #ea580c; }
        .kpi-card.soon .kpi-value { color: #d97706; }
        .kpi-card.success .kpi-value { color: #059669; }

        /* Veri Tabloları */
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9.5px; }
        .data-table th { 
            background-color: #1e293b; 
            color: #ffffff; 
            font-weight: bold; 
            padding: 8px; 
            text-align: left; 
            font-size: 9px; 
            text-transform: uppercase; 
        }
        .data-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .data-table tr:nth-child(even) { background-color: #f8fafc; }
        
        /* Durum Etiketleri */
        .badge { padding: 3px 6px; border-radius: 10px; font-size: 8px; font-weight: bold; display: inline-block; }
        .badge-danger { background-color: #ffe4e6; color: #be123c; border: 1px solid #fecdd3; }
        .badge-warning { background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
        .badge-soon { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        
        .firm-name { font-weight: bold; color: #0f172a; font-size: 10px; }
        .text-muted { color: #64748b; }
        
        /* Sayfa Numarası Alt Bilgisi */
        .footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            height: 30px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <!-- Üst Başlık -->
    <div class="header-block">
        <h1>Çevre Mevzuat Takip Raporu</h1>
        <p>Tarih: {{ $today }} &nbsp;|&nbsp; Raporu Oluşturan: {{ $userName }}</p>
    </div>

    <!-- Özet Kartları -->
    <div class="section-title">1. Yönetici Özeti</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-card danger" style="width: 25%;">
                <div class="kpi-label">Gecikmiş İşler</div>
                <div class="kpi-value">{{ $stats['overdue'] }}</div>
            </td>
            <td class="kpi-card warning" style="width: 25%;">
                <div class="kpi-label">Kritik (14 Gün)</div>
                <div class="kpi-value">{{ $stats['crit'] }}</div>
            </td>
            <td class="kpi-card soon" style="width: 25%;">
                <div class="kpi-label">Yaklaşan (30 Gün)</div>
                <div class="kpi-value">{{ $stats['soon'] }}</div>
            </td>
            <td class="kpi-card success" style="width: 25%;">
                <div class="kpi-label">Güncel</div>
                <div class="kpi-value">{{ $stats['ok'] }}</div>
            </td>
        </tr>
    </table>

    <!-- Danışman Özeti -->
    <div class="section-title">2. Danışman Bazlı İş Yükü</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Danışman</th>
                <th style="text-align: center; color: #f87171;">Gecikmiş</th>
                <th style="text-align: center; color: #fb923c;">Kritik</th>
                <th style="text-align: center; color: #fbbf24;">Yaklaşan</th>
                <th style="text-align: center; color: #34d399;">Güncel</th>
            </tr>
        </thead>
        <tbody>
            @foreach($consultantStats as $name => $stat)
            <tr>
                <td class="firm-name">{{ $name }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $stat['overdue'] }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $stat['crit'] }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $stat['soon'] }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $stat['ok'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Detaylı Liste -->
    <div class="section-title" style="page-break-before: auto;">3. Aksiyon Gerektiren İşler Listesi</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Tesis Adı / Danışman</th>
                <th style="width: 30%;">Konu</th>
                <th style="width: 15%;">Durum</th>
                <th style="width: 15%;">Son Tarih</th>
                <th style="width: 15%;">Not</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detailedTasks as $task)
            <tr>
                <td>
                    <div class="firm-name">{{ $task['firm_name'] }}</div>
                    <div class="text-muted" style="font-size: 8px;">{{ $task['consultant'] }}</div>
                </td>
                <td style="font-weight: bold; color: #334155;">{{ $task['topic'] }}</td>
                <td>
                    @if($task['state'] == 'overdue')
                        <span class="badge badge-danger">Gecikmiş</span>
                    @elseif($task['state'] == 'crit')
                        <span class="badge badge-warning">Kritik</span>
                    @else
                        <span class="badge badge-soon">Yaklaşan</span>
                    @endif
                    <div class="text-muted" style="font-size: 8px; margin-top: 3px; font-weight: bold;">({{ $task['days'] }} gün)</div>
                </td>
                <td class="text-muted" style="font-weight: bold;">{{ $task['due'] }}</td>
                <td style="font-size: 8px; color: #64748b; font-style: italic;">
                    {{ $task['note'] ?: '-' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; padding: 15px; font-weight: bold; color: #059669;">
                    Harika! Şu an sistemde aksiyon gerektiren açık bir iş bulunmuyor.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Sayfa Numarası Scripti (DomPDF) -->
    <div class="footer">
        <script type="text/php">
            if (isset($pdf)) {
                $text = "Sayfa {PAGE_NUM} / {PAGE_COUNT}";
                $size = 9;
                $font = $fontMetrics->get_font("DejaVu Sans", "normal");
                $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
                $x = ($pdf->get_width() - $width) / 2;
                $y = $pdf->get_height() - 35;
                $pdf->page_text($x, $y, $text, $font, $size, array(148, 163, 184));
            }
        </script>
        <span style="display:block; margin-top: -12px;">Çevre Takip Sistemi &copy; {{ date('Y') }}</span>
    </div>

</body>
</html>