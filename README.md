# Çevre Takip ve Atık Takip Portalı

Ulus Çevre'nin çevre danışmanlığı yaptığı tesisleri, mevzuat yükümlülüklerini, ziyaret takvimini ve atık taşıma kayıtlarını takip etmek için kullanılan web uygulamasıdır. Uygulama **Çevre Takip** ve **Atık Takip** modüllerini ayrı çalışma alanları olarak sunar.

Canlı ortam kurulumu ve dağıtım adımları için [DEPLOYMENT.md](DEPLOYMENT.md) dosyasına bakın.

## Modüller

### Çevre Takip

- Genel bakışta gecikmiş, kritik ve yaklaşan yükümlülükleri görüntüleme.
- Tesis kayıtları, danışman atamaları, arşiv ve tesis değişiklik geçmişi.
- Mevzuat takvimi, tesis ziyaretleri, yönetici onayları ve görsel raporlar.
- Çevre izin/lisans, GFB, ÇED, atıksu görüşü ve diğer belge takibi.
- Excel içe/dışa aktarma, duyurular, kullanıcı yönetimi, sistem ayarları ve yedekler.

### Atık Takip

- Atık üreticisi ve atık işleme tesisi kayıtları.
- Atık taşıma kayıtları ve taşıma geçmişi.
- Taşıma listesinde haftalık (Pazartesi–Pazar), aylık ve tüm kayıtlar filtreleri.
- Haftalık yapılacak taşımalarda kayıtları güne göre daraltılabilir biçimde görüntüleme; her gün için farklı tesis sayısı ve atık çeşitliliğini özetleme.
- Araç istatistiklerini aylık ve yıllık inceleme; her iki görünümde de hedefleri takip etme.
- İstatistik ekranıyla sınırlı kullanıcı rolü.

## Güncel iş akışları

### Tesis ve belge bilgileri

- Tesisin ekranda görünen adı Türkçe yazım ve okunabilirlik gözetilerek biçimlendirilir. Bu yalnızca görüntüleme katmanında yapılır; Excel'den gelen ve veri tabanında tutulan ad değiştirilmez. Böylece mevcut kaynak değer korunurken arayüzde boşluklar ve yaygın şirket unvanları tutarlı gösterilir.
- İzin ve lisans konuları tesis detayında ayrı, çoklu seçim listelerinde tutulur. Konular katalogdan gelir; tesisin seçimi düzenlenebilir ve GFB'den izin sürecine aktarılabilir.
- Çevre izin/lisans başlığı altında belge türü seçilebilir: GFB veya Çevre İzin/Lisans. Eski kayıtların bilgileri korunur.
- GFB, ilk kez izin/lisans alacak tesislere özgü, tekrarlanmayan belge olarak izlenir. Başvuru son günü GFB başlangıcından 180 gün sonrası kabul edilir; uyarılar son tarihten 60 ve 30 gün önce hesaplanır.
- İzin konularının ve lisans konularının ekrandaki seçenekleri katalog üzerinden yönetilir. 2026 belge bedeli kataloğu ayrı yapılandırmadadır; tesis detayındaki konu seçimleriyle aynı katalog yapısını paylaşır.
- ÇED kararlarında “ÇED GEREKLİ DEĞİLDİR” seçeneği bulunur.
- Atıksu görüşünde OSB dışındaki tesisler için isteğe bağlı yıllık geçerlilik doğrulaması vardır. OSB tesislerinde olağan yenileme takibi sürer.
- ÇİLY Ek Kapsamı bilgisi, izin/lisans kapsamından bağımsız tesis detay alanıdır.

### Ziyaret takvimi

- Ziyaretler tesis, danışman, tarih ve not bilgileriyle kaydedilir; tesis adı yazılarak arama/öneri listesinden seçim yapılabilir.
- Takvimde not içeren ziyaretler görsel olarak ayırt edilir. Yönetici takviminde ay ve hafta görünümleri bulunur.
- Bakanlık sistemine aktarım durumu her ziyaret için “aktarılacak”, “aktarıldı” veya önceki aktarım sonrası kayıt değişmişse “güncel hali aktarılmalı” olarak izlenebilir. Liste üzerinden kaydı açmadan durum güncellenebilir.
- Danışman için aynı güne ikinci ziyaret ataması yönetici onayına ve açıklama notuna bağlıdır.
- Aylık ziyaret sınırı ek kapsamı Ek-1 olan tesislerde iki, diğer veya kapsamı belirtilmemiş tesislerde bir ziyarettir. Sınırın aşılması için açıklama ve yönetici onayı gerekir; onaylanana kadar ziyaret takvime eklenmez. Yönetici doğrudan istisna uygulayabilir.
- Ayın 20'sinden itibaren ana sayfada takip eden ayın ziyaret planı hatırlatması gösterilir. Danışmana kendi aktif tesislerinden henüz ziyaret kaydı girilmemiş olanlar; yöneticiye ise tüm danışmanların eksik tesis sayıları sunulur. Toplam sayıya tıklanınca tüm eksik tesisler, yönetici görünümünde danışman adına veya danışman sayısına tıklanınca o danışmanın eksik tesisleri açılır. Tesis için takip eden ayda ilgili danışmana en az bir ziyaret girildiğinde o tesis hatırlatma listesinden çıkar.
- Kayıtlı bir ziyaretin silinmesi danışman tarafından talep edilir; yönetici talebi inceleyip onaylayabilir veya reddedebilir. Silme ve aylık sınır istisnası süreçlerinde e-posta gönderilmez; talepler uygulamadaki yönetici takviminde gösterilir.

### Duyurular ve doğrulamalar

- Önemli kullanıcı değişiklikleri duyuru olarak yayımlanabilir. Duyurular ana sayfada ve ilgili sayfada görünür; ayrıntı açılıp kapatılabilir.
- Duyurularda yayımlanma tarihi ve kullanıcı duyuruyu onayladıysa onay tarihi gösterilir. Kullanıcı “Okudum, anladım” ile okuduğunu kaydeder.

## Mimari ve veri yapısı

- **Sunucu:** PHP 8.3+, Laravel 13.
- **Arayüz:** Vue 3, Inertia.js, Tailwind CSS 4 ve Vite Plus.
- **Veritabanı:** Yerel geliştirmede SQLite; canlı ortamda yapılandırmaya göre MySQL/MariaDB.
- **Başlıca paketler:** Laravel Fortify, PhpSpreadsheet, DomPDF ve Spatie Laravel Backup.
- **Grafikler ve raporlar:** Chart.js, Vue Chart.js ve PDF/görsel dışa aktarma araçları.

Tesisin esnek profil verilerinin önemli bir kısmı firms tablosundaki data JSON alanında tutulur. Ziyaretler, kullanıcılar, onay talepleri ve günlük işlem kayıtları ayrı modeller/tablolardadır. Tesis profilindeki izin/lisans konu seçimleri profile.permitTopicKeys ve profile.licenseTopicKeys alanlarıyla saklanır.

Mevzuat yükümlülüklerinin hesaplanması app/Services/ComplianceEngine.php içindedir. Varsayılan ve dinamik yükümlülükleri birlikte almak için ComplianceEngine::getAll() kullanılmalıdır.

API uçları oturum ve Inertia davranışlarıyla uyum için routes/web.php içinde tanımlanmıştır. Kullanıcıya ve yöneticiye özel sayfalar oturum/rol kontrolleriyle korunur. Kullanıcı hesapları yönetici tarafından oluşturulur; genel kayıt ve parola sıfırlama akışları açık değildir.

## Yedekleme ve dağıtım

- Yedekleme Spatie Laravel Backup ile yapılır. Zamanlanmış görevler her gün 02.00'de yalnızca veritabanını, Pazar günü 02.15'te tam yedeği çalıştırır; temizleme günlük 01.30'da, yedek denetimi günlük 02.30'da çalışır. Sunucunun Laravel scheduler'ı çalışır durumda olmalıdır.
- Canlı dağıtım betiği veritabanı yedeği alır, önceki ön yüz derlemesini korur, dağıtım paketini uygular, yapılandırma/rota/görünüm önbelleklerini temizler ve portalın HTTP yanıtını kontrol eder.
- Canlıya aktarım için PowerShell betiği scripts/deploy-live.ps1, sunucu tarafındaki uygulama betiği scripts/deploy-live-remote.sh kullanılır. Sunucuya erişim anahtarı, yedek hedefi ve diğer gizli değerler depoda tutulmamalı; yerel yapılandırmada kalmalıdır.
- Dağıtım adımlarının, yedek geri yükleme yaklaşımının ve sunucu ön koşullarının ayrıntısı [DEPLOYMENT.md](DEPLOYMENT.md) içinde yer alır.

## Yerel geliştirme

Ön koşullar: PHP 8.3 ve Composer; Node.js/NPM ve Vite Plus. Proje kökünde bağımlılıkları kurun, .env.example dosyasını .env olarak kopyalayıp uygulama anahtarı oluşturun, veritabanı ayarlarını yapın ve migration'ları çalıştırın. Ardından geliştirme sunucusunu ve ön yüz derleyicisini başlatın.

Önemli Composer/NPM komutları:

- composer install — PHP bağımlılıklarını kurar.
- php artisan migrate — veritabanı şemasını günceller.
- npm install — ön yüz bağımlılıklarını kurar.
- npm run dev — yerel arayüz geliştirme sunucusunu başlatır.
- npm run build — canlıya alınabilir ön yüz çıktısını üretir.
- php artisan app:create-admin — ilk yönetici hesabını etkileşimli ve geçici parola ile oluşturur.

Canlı veritabanında migration çalıştırırken dağıtım kılavuzunu izleyin; migrate:fresh mevcut verileri siler ve canlıda kullanılmamalıdır.

## İlgili dosyalar

- DEPLOYMENT.md — canlı sunucu kurulumu ve dağıtımı.
- routes/web.php, routes/console.php — web rotaları ve zamanlanmış işler.
- app/Services/ComplianceEngine.php — mevzuat yükümlülük motoru.
- config/environment_permit_catalog.php — izin/lisans konu ve ücret kataloğu.
- config/portal_announcements.php — uygulama duyuruları.
- scripts/deploy-live.ps1, scripts/deploy-live-remote.sh — canlı dağıtım yardımcıları.
