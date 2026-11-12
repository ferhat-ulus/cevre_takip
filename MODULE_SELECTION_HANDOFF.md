# Proje Süreci Devir Özeti — Baştan Bugüne

> Bu belge, erişilebilen oturum checkpoint'leri ve en son doğrulanmış çalışma durumundan hazırlanmış teknik devir özetidir; tüm sohbetin birebir dökümü değildir. Hassas bilgiler (parola, token, anahtar) özellikle eklenmemiştir.

## 1. Proje ve hedef

Proje, danışmanların tesis, çevre yükümlülüğü, emisyon ölçümü/raporu, ziyaret ve rapor süreçlerini takip ettiği Laravel + Vue 3 + Inertia uygulamasıdır. Kullanıcı; Excel veri akışı, emisyon/takvim kuralları, dashboard ve yönetim ekranları üzerinde ardışık iyileştirmeler yaptı. Son hedef, aynı uygulama alanında modül seçimiyle Çevre Takip'i açmak ve ileride Atık Takip ile Teklif Takip modüllerini eklemektir.

## 2. Süreç boyunca yapılan ana işler

### Excel ve eski durum temizliği

- PhpSpreadsheet dışa aktarımında hücre nesnesi kopyalanırken oluşan `Worksheet no longer exists` hatası giderildi; koordinat bazlı kopyalama ve şablonsuz dışa aktarım dalı korundu.
- Yüklenen Excel şablonunun sütun eşlemesi ve bozuk dış bağlantılı `#REF!` açılır listeleri ele alındı.
- Eski “Süreçte” durumu kullanıcıya gösterilmeyecek ve açılır listelerde sunulmayacak şekilde temizlendi. Excel'den gelirse önizlemede uyarı veriliyor; onaylanan içe aktarmada ilgili eski durum/tarih temizleniyor ve “Veri Yok” kabul ediliyor.
- Excel akışında formül sütunlarının korunması, eski kayıtların içe/dışa aktarımda normalleştirilmesi ve ilgili regresyon testleri eklendi.
- Gerçek `storage/app/templates/master_template.xlsx` dosyasının seçenek sayfası doğrulaması checkpoint'te tamamlanmamış görünüyordu; bu kontrolün daha sonra çözülüp çözülmediği yeniden teyit edilmeli.

### Genel arayüz ve dashboard

- Ortak uygulama kabuğu daraltılabilir/açılır gruplu bir sol menüye, mobil menüye ve tek ana içerik kaydırma alanına göre düzenlendi.
- “Rapor İndir” menü sayfası kaldırıldı; PDF indirme Raporlar'a, Excel dışa aktarma Excel İşlemleri'ne taşındı. Eski `/rapor-indir` adresi `/raporlar`'a yönlendiriliyor.
- Dashboard'a toplam tesis, Ek-1, Ek-2 ve kapsam dışı tesis sayaçları eklendi. Danışman görünümü kendi tesis kapsamıyla sınırlandırılmalı.
- Yeni tesis ekleme dashboard'dan kaldırılarak Tesisler sayfasında bırakıldı.
- Tesis detayındaki geri davranışı: aynı oturumdaki önceki sayfaya dön, doğrudan açılışta Genel Bakış'a git.
- Genel Bakış kartları kompakt hale getirildi.

### Emisyon süreçleri

- İzne esas emisyon ölçümü ve izne esas emisyon raporu, tek seferlik başlangıç/bilgi alanları olarak ayrıldı.
- İki yılda bir yapılan teyit ölçümü mevcut `emisyonOlcum` alanında takip ediliyor. İlk teyit tarihi yoksa periyot izne esas ölçüm tarihinden başlıyor; sonraki dönem teyit tarihinden hesaplanıyor.
- Varsayılan periyot 720 gündür; Excel kolon eşlemesi, controller, mevzuat hesaplaması ve Emisyon ekranı bu ayrımı yansıtacak şekilde düzenlendi.
- Önceki kontrolde mevcut veritabanındaki tesislerin emisyon tarih alanları boştu; içe aktarılmış tarihleri yeniden kontrol etmek gerekir.

### Danışman ve tesis geçmişi

- Yöneticiler için Danışman Takibi ekranı eklendi: danışman portföyü, önceki danışman, tesis atama geçmişi/gerekçe ve atanmamış tesisler.
- Toplu tesis devri, gerekçe kaydı ve pasife alma/rol değiştirme öncesi portföy devri kontrolleri eklendi.
- `firm_logs` için danışman değişikliği alanları migration ile eklendi: `from_consultant`, `to_consultant`, `reason`.
- Mevzuat listesinin aynı istek içinde tekrar hesaplanması önbelleğe alındı.
- Kullanıcının `Danışman Değişiklik Takibi.csv` dosyası incelendi, **henüz sisteme aktarılmadı**. Bilinen yapı: yaklaşık 23 MB, 225 veri satırı, 21 sütun, `;` ayraçlı CP1254; önceki danışman/tarih sütun başlıkları tekrarlanıyor. Sütunları başlık sözlüğüyle değil indeks/sırasıyla okumak gerekir. Ay/yıl düzeyindeki tarihleri güne tamamlayarak uydurma tarih üretmeyin; önce tesis eşleşmelerini ve belirsiz kayıtları önizleyin.

### PDF ve takvim

- Büyük rapor PDF'sinde bellek taşması yeniden üretildi; tesisler 100'lük gruplarla işleniyor. PDF'de en öncelikli 300 iş satırı tutuluyor, daha fazla kayıt varsa PDF'de açıklama veriliyor; tam liste Excel'den alınabiliyor.
- Ziyaret planı yarından itibaren yapılabiliyor; geçmiş tarihli ziyaret silinemiyor; bugünkü ziyaretler yalnızca Türkiye saatiyle 16.00'dan önce silinebiliyor. Sunucu ve arayüz `Europe/Istanbul` saatine göre kontrol ediyor.

### Kimlik doğrulama ve testler

- Uygulamanın gerçek giriş akışı kullanıcı adı/parola kullanan özel `/api/login` endpoint'idir.
- Fortify kayıt, parola sıfırlama, e-posta doğrulama, 2FA ve passkey özellikleri uygulamada desteklenmediğinden devre dışı bırakıldı; test fabrikası ve feature testleri gerçek kullanıcı şeması/akışıyla hizalandı.
- Önceki checkpoint'lerde tam test sayıları farklıdır; güncel sonucu aşağıdaki “Son doğrulamalar” bölümündeki sayılardan okuyun.

### Hosting ve veritabanı

- Natro paylaşımlı hosting için aktarılan kısıtlar: PHP sürümü ana site/subdomain'leri etkiliyor; SSH/Composer ve Laravel desteği yok; cron var; SSL ve yedekleme koşulları ayrıca çözülmeli.
- Ana sitenin PHP'si 8.3'e güncellendi; bu tek başına Laravel kurulum gereksinimlerini çözmüyor. Üretime geçiş yapılmış değildir; Laravel destekli sunucu, güvenli web kökü, veritabanı, SSL ve yedekleme gerekir.
- Projede başlangıç veritabanı SQLite'tır; MySQL/MariaDB bağlantısı için yerel uyumluluk düzenlemeleri ve açıklamalar eklendi. Excel veri temizleme işlemi SQLite'a özel SQL'den çıkarıldı; backup yapılandırması etkin bağlantıyı esas alıyor.
- Yerel PHP 8.3'te `pdo_mysql` etkinleştirildi. MySQL 8.4.9 taşınabilir sunucusu yalnız `127.0.0.1:3306` üzerinde test edildi; bu Natro'nun MySQL sürümünü temsil ettiği kesin değildir.
- Yerel MySQL test veritabanları `cevre_takip_mysql_test` ve ayrı test koşusu için `cevre_takip_mysql_ci` olarak oluşturuldu. MySQL Windows servisi olarak kurulmadı; bilgisayar yeniden başlatıldığında ayrıca başlatılması gerekebilir. `pdo_mysql`, `C:\php\php.ini` içinde etkinleştirildi; önceki ini yedeği `C:\php\php.ini.before-pdo-mysql.bak` konumundadır.

### Boş sürüm ve paralel çalıştırma

- Dolu uygulamayı korumak için `release/empty-admin-only` dalı ayrı worktree'de hazırlandı. Seeder boş kurulumda `.env` ile tanımlı ilk admin'i oluşturur ve mevcut kullanıcıyı ezmez.
- Boş SQLite kopyası ve daha sonra ayrı boş MySQL test veritabanı oluşturuldu; dolu SQLite veritabanı boş sürümde kullanılmadı.
- Bilinen yerel adresler: dolu SQLite `http://127.0.0.1:8000`, boş SQLite `http://127.0.0.1:8001`, boş MySQL `http://127.0.0.1:8002`. Son kontrolde 8002 giriş sayfası `200`, oturumsuz dashboard `302` verdi; başka zamanda kullanmadan önce portların hâlâ açık olduğunu doğrulayın.
- Boş worktree'de Vite build için ignore edilen Wayfinder çıktıları bir aşamada eksikti; build öncesinde gerekirse `php artisan wayfinder:generate` çalıştırın.
- GitHub'da `v2026.11.09.1` release'i yayımlanmıştı. Boş sürümde `v2026.11.09.2` yerel etiketi ve `release/empty-admin-only` dalı hazırlanmıştı; uzak etiketin durumu sonradan doğrulanmamış olabilir.

## 3. En son özellik: modül seçimi ve geçiş kapısı

Akış: giriş → modül seçimi → Çevre Takip. Atık Takip ve Teklif Takip şimdilik devre dışı “Yakında” kartlarıdır.

- `POST /api/login` başarılı olunca session yenileniyor, önceki `active_module` siliniyor ve `/moduller` dönülüyor.
- `GET /moduller` seçim ekranını gösterirken `active_module` değerini temizliyor.
- Çevre Takip kartı `POST /moduller/cevre-takip` isteği yapıyor; sunucu `active_module=environment` yazıp dashboard'a yönlendiriyor.
- `EnsureEnvironmentModuleSelected` middleware'i seçim yapılmadan uygulama sayfalarını `/moduller`'e yönlendiriyor; JSON API isteklerine `403` veriyor.
- Sol menüdeki “Uygulamalar” grubu kaldırıldı. Uygulama içindeki **Modül değiştir** düğmesi üst çubuğun sağında; seçim ekranına dönünce tekrar modül seçmek gerekiyor.
- Şu anki kontrol **oturum bazlı akış kapısıdır**, kullanıcı/rol bazında modül yetkilendirmesi değildir. Yeni modüller açılmadan önce kullanıcı/rol izinleri backend'de modellenmeli; sadece kartı etkinleştirmek yeterli değildir.

### Modül akışındaki önemli dosyalar

- `routes/web.php`: modül rotaları ve korumalı rota grubu.
- `app/Http/Middleware/EnsureEnvironmentModuleSelected.php`: seçilmemiş modüle erişim engeli.
- `app/Http/Controllers/Api/AuthController.php`: login yönlendirmesi ve önceki seçimin sıfırlanması.
- `resources/js/pages/ModulSecimi.vue`, `resources/js/pages/auth/Login.vue`, `resources/js/layouts/AppLayout.vue`: seçici ekran, login yönlendirmesi ve modül değiştir UI'si.
- `tests/Feature/Auth/AuthenticationTest.php`: seçim olmadan sayfa/API erişimi, seçim sonrası erişim, seçim ekranına dönüş ve login testleri.
- `tests/TestCase.php`: uygulama feature testlerinde `actingAs()` seçilmiş Çevre Takip oturumunu varsayar; geçiş kapısı testleri session değerini özellikle temizler.

## 4. Çalışma alanı ve korunması gerekenler

1. Ana worktree: `C:\Users\Ferhat\cevre-takip-php`, dal `main`, HEAD en son bilinen `92a44a3`.
2. Boş worktree: `C:\Users\Ferhat\cevre-takip-php-empty`, dal `release/empty-admin-only`, HEAD en son bilinen `8122a86`.

Her iki worktree'de de modül akışı değişiklikleri commit edilmemiştir. Ana worktree'de ayrıca önceki MySQL uyumluluk işi için `.env.example`, `README.md`, `app/Http/Controllers/Api/ExcelController.php` ve `config/backup.php` değişiklikleri vardır. `MODULE_SELECTION_HANDOFF.md` bu devir çalışmasında eklenmiştir. `git reset`, `git checkout` veya geniş kapsamlı geri alma yapmayın; gerçek SQLite veritabanını ve mevcut kullanıcı değişikliklerini koruyun.

Önceki boş worktree hazırlığında bazı Artisan komutları PowerShell çalışma klasörü nedeniyle yanlışlıkla ana worktree'de çalıştırıldı. Sonrasında ana veritabanı incelendi; boş sürüm admin'inin ana DB'ye eklenmediği ve gerçek verilerin seed ile ezilmediği doğrulandı. Ana `.env` içindeki `APP_KEY`, 2 Kasım tarihli yerel yedekten geri yüklendi; bu anahtarın o tarihten sonra değiştirilip değiştirilmediği kesin değil. `APP_KEY`'i yeniden üretmeyin; şifrelenmiş ayarları kullanan bir değişiklikten önce anahtar/deşifreleme uyumluluğunu doğrulayın.

PowerShell her çağrıda ana dizinde başlar. Boş worktree komutundan önce açıkça `Set-Location C:\Users\Ferhat\cevre-takip-php-empty` kullanın. MySQL ve SQLite testlerinin hangi `.env`/ortam değişkeniyle çalıştığını testten önce doğrulayın; testleri gerçek kullanıcı veritabanında çalıştırmayın. Bu belgede hiçbir parola veya veritabanı sırrı bulunmaz.

## 5. Son doğrulamalar

Son modül seçimi düzenlemesi sonrasında:

- Ana worktree: `php artisan test` — 92 test, 64 başarılı, 28 atlandı; 487 assertion.
- Boş worktree: `php artisan test` — 96 test, 68 başarılı, 28 atlandı; 501 assertion.
- Her iki worktree'de `npm run build` başarılı.
- Yeni modül rotaları iki worktree'de de `route:list` ile görüldü.
- 8002 kontrolü: login `200`, oturumsuz dashboard `302`.

Bu sayılar farklı worktree'lerdeki test setlerinden gelir; gelecekteki çalışmada yeni test sonucu tekrar çalıştırılmalı ve bu bölüm güncellenmelidir.

## 6. Açık işler ve sonraki adımlar

1. CSV danışman geçmişini içe aktarma henüz yapılmadı. Önce güvenli eşleşme/şüpheli/eşleşmeyen tesis önizlemesi çıkarılmalı, tarih hassasiyeti korunmalı ve kullanıcı onayı olmadan veritabanına yazılmamalı.
2. Atık Takip/Teklif Takip etkinleştirilmeden önce backend kullanıcı/rol modül izinleri tasarlanmalı; rotalar, API'ler ve doğrudan URL erişimi test edilmeli.
3. Yerel MySQL 8.4 testinden üretim uyumluluğu sonucu çıkarılmamalı. Natro veya yeni sunucunun gerçek MySQL/MariaDB sürümünde ayrıca test gerekir.
4. Natro'ya dağıtım yapılmış değildir; PHP 8.3 değişikliği tek başına Laravel'i çalıştırmaz. Üretim hedefi ve altyapı erişimi ayrıca netleştirilmeli.
5. Gerçek Excel şablonundaki validation/açılır liste kaynaklarının iç çalışma kitabında kaldığı bir kez daha doğrulanmalı; önceki checkpoint'te bu son doğrulama açık kalmıştı.
