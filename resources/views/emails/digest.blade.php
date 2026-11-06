<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Open Sans', Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background-color: #16a34a; padding: 20px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 20px; }
        .content { padding: 20px; }
        .greeting { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
        .summary { margin-bottom: 20px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-right: 5px; }
        .badge-danger { background-color: #ffe4e6; color: #be123c; }
        .badge-warning { background-color: #ffedd5; color: #c2410c; }
        .badge-soon { background-color: #fef3c7; color: #b45309; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { text-align: left; padding: 10px; background-color: #f1f5f9; font-size: 12px; color: #64748b; border-bottom: 1px solid #e2e8f0; }
        .table td { padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
        .footer { text-align: center; padding: 15px; font-size: 11px; color: #94a3b8; background-color: #f8fafc; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Çevre Mevzuat Takip Özeti</h1>
        </div>
        <div class="content">
            <div class="greeting">Merhaba {{ $user->name }},</div>
            <p>Sistemdeki güncel mevzuat durumunuz aşağıdadır.</p>
            
            <div class="summary">
                <span class="badge badge-danger">{{ $stats['overdue'] }} Gecikmiş</span>
                <span class="badge badge-warning">{{ $stats['crit'] }} Kritik</span>
                <span class="badge badge-soon">{{ $stats['soon'] }} Yaklaşan</span>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>Tesis / Konu</th>
                        <th>Durum</th>
                        <th>Kalan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(array_slice($tasks, 0, 15) as $task)
                        @php 
                            $f = $task[0]; 
                            $k = $task[1]; 
                            $e = $task[2]; 
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $f['name'] }}</strong><br>
                                <span style="color: #64748b; font-size: 11px;">{{ \App\Services\ComplianceEngine::obl($k)['n'] }}</span>
                            </td>
                            <td>
                                <span class="badge {{ in_array($e['state'], ['missing', 'overdue'], true) ? 'badge-danger' : ($e['state'] == 'crit' ? 'badge-warning' : 'badge-soon') }}">
                                    {{ \App\Services\ComplianceEngine::STATE_L[$e['state']] }}
                                </span>
                            </td>
                            <td>
                                {{ $e['days'] !== null ? $e['days'] . ' gün' : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            
            @if(count($tasks) > 15)
                <p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 15px;">
                    ... ve {{ count($tasks) - 15 }} iş daha. Detaylar için sisteme giriş yapın.
                </p>
            @endif

            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ config('app.url') }}" style="background-color: #16a34a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Sisteme Git</a>
            </div>
        </div>
        <div class="footer">
            Bu e-posta sistem tarafından otomatik olarak gönderilmiştir.
        </div>
    </div>
</body>
</html>