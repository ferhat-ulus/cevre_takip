<?php

return [
    'wastewater-annual-verification' => [
        'published' => true,
        'context' => 'wastewater',
        'title' => 'OSB dışı tesislerde atıksu görüşü için yıllık doğrulama (Kanalizasyon ve Vidanjör)',
        'paragraphs' => [
            'Talep üzerine yenilenen atıksu görüşleri için Tesis Detayı → Atıksu Görüşü bölümünde Yıllık doğrulamayı aç seçeneğini kullanabilirsiniz.',
            'Görüşün hâlâ geçerli olduğunu kontrol ettikten sonra Geçerliliği doğrula düğmesine basın. Portal belge tarihini korur ve son doğrulamadan 11 ay sonra Genel Bakış’ta hatırlatır.',
            'Belge düzenli yenileme gerektiriyorsa Yenileme takibine dön düğmesini kullanın. OSB tesislerinde periyodik yenileme takibi devam eder.',
        ],
    ],
    'gfb-environmental-permit-tracking' => [
        'published' => true,
        'context' => 'portal',
        'title' => 'Çevre İzin/Lisans sürecinde GFB takibi',
        'paragraphs' => [
            'Tesis detaylarındaki Çevre İzin/Lisans başlık seçimine Geçici Faaliyet Belgesi (GFB) seçeneği eklendi.',
            'GFB seçildiğinde belge tarihi girilebilir. Sistem, Çevre İzin/Lisans başvurusunun son gününü GFB tarihinden 180 gün sonrası olarak hesaplar ve bu son tarihten 60 ve 30 gün önce uyarı verir. GFB’nin geçerlilik süresi de ekranda bir yıl olarak gösterilir.',
            'GFB süreci olan tesisler için bu seçenek kullanılmalıdır.',
        ],
    ],
    'environment-permit-license-topics' => [
        'published' => true,
        'context' => 'portal',
        'title' => 'Çevre İzin ve Lisans Konuları',
        'paragraphs' => [
            'Çevre İzin ve Lisans Yönetmeliği kapsamında olan tesislerin konuları tesis detay sayfasından seçilebiliyor. Kapsama giren tesislerin konuları bu kısımda seçilerek değişikliğin kaydedilmesi gerekmektedir.',
        ],
    ],
];
