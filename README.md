# Çevre Takip Sistemi (Ulus Çevre)

Bu proje, çevre danışmanlığı yürütülen tesislerin mevzuat bağlamında takip edilmesi, uyarıların yönetilmesi ve raporlanması için geliştirilmiş bir web uygulamasıdır.

## 🛠️ Kullanılan Teknolojiler
- **Backend:** Laravel (PHP 8+)
- **Frontend:** Vue 3 (Composition API), Inertia.js
- **Tasarım:** Tailwind CSS (v4 kullanılıyor, `@tailwindcss/vite` eklentisi mevcut)
- **Veritabanı:** SQLite
- **Derleyici:** Vite (TypeScript kullanılıyor: `vite.config.ts` ve `app.ts`)
- **Yedekleme:** `spatie/laravel-backup` paketi

## 📂 Önemli Mimari Detaylar ve Özel Yapılar

### 1. Veri Saklama (JSON Yapısı)
Standart ilişkisel veritabanı yerine, esneklik sağlamak adına `Firm` (Tesis) modelindeki temel bilgiler `data` adı verilen bir **JSON sütununda** tutulur.
- Örnek: `$firm->data['name']`, `$firm->data['consultant']`, `$firm->data['items']`
- Sorgularda JSON yapısı kullanılır: `Firm::where('data->consultant', $user->name)->get()`

### 2. Mevzuat Motoru (ComplianceEngine)
Projenin beyni `app/Services/ComplianceEngine.php` dosyasıdır. 
- Tesislerin mevzuat sürelerini (tebliğ tarihleri, periyotlar, kritik eşikler) hesaplar.
- Varsayılan mevzuatlar bu dosyada sabittir (`OBLIGATIONS` sabiti). Ancak "Sistem Ayarları" üzerinden eklenen dinamik mevzuatlar veritabanındaki `custom_obligations` ayarından çekilerek birleştirilir.
- Tüm mevzuatları (varsayılan + dinamik) çekmek için **her zaman `ComplianceEngine::getAll()`** metodu kullanılmalıdır.

### 3. Loglama Sistemi
- **Aktivite Logları:** `ActivityLog` modeli üzerinden sistem genelindeki (kullanıcı ekleme, ayar değiştirme, ziyaret oluşturma, yedek alma vb.) işlemler kaydedilir.
- **Tesis Logları:** `FirmLog` modeli üzerinden sadece bir tesisin danışman değişimi gibi kendi iç geçmişi kaydedilir.

### 4. Yetkilendirme ve Güvenlik (Middleware)
- Rotalarda `auth` (giriş yapmış olma) ve `admin` (Sistem Yöneticisi olma) korumaları vardır.
- Admin koruması `app/Http/Middleware/EnsureUserIsAdmin.php` dosyasındadır ve rol kontrolü büyük/küçük harf duyarsız (`strtolower`) yapılır.
- `app/Http/Middleware/HandleInertiaRequests.php` dosyası, kullanıcı oturumunu ve rollerini (her zaman küçük harf olarak) Vue tarafına aktarır.
- Kullanıcı hesapları yönetici tarafından oluşturulur ve giriş kullanıcı adıyla yapılır. Genel kayıt, parola sıfırlama, e-posta doğrulama, iki aşamalı giriş ve passkey akışları henüz etkin değildir.
- Yetkisiz erişimlerde 403 hatası yerine kullanıcı dostu yönlendirmeler ve "Toast" (kırmızı uyarı) mesajları gösterilir.

### 5. Rotalar (Routing)
- SPA (Single Page Application) mantığı gereği ve oturum (session) yönetiminin sorunsuz çalışması için API rotaları dahi `routes/web.php` içerisinde tanımlanmıştır. `routes/api.php` kullanılmamaktadır.
- Inertia.js ile sayfa yönlendirmeleri (redirect) yapılırken dikkatli olunmalı, JSON dönen yerlerde `return response()->json()` kullanılmalıdır.

## 🚀 Tamamlanan Ana Modüller
1. **Dashboard (Genel Bakış):** Gecikmiş, Kritik (14 Gün), Yaklaşan (30 Gün) işler.
2. **Tesisler (Firms):** Tesis ekleme, silme, arşivleme ve tesis detay sayfası. Tesis detayında mevzuat takibi (Tarih, Kapsam Dışı, Notlar) yapılır. Yetki kontrolü mevcuttur.
3. **Ziyaret Takvimi:** FullCalendar kullanılarak yapıldı. Hafta sonu ve resmi tatil kontrolü vardır. Çift aşamalı silme onayı mevcuttur.
4. **Kullanıcı Yönetimi:** Admin, danışmanları yönetebilir (pasife alma/loglama, şifre sıfırlama).
5. **Sistem Ayarları:** Süreler, eşikler ve dinamik mevzuat ekleme/silme (Admin yetkisi gerektirir).
6. **Excel İşlemleri:** Şablonlu veya şablonsuz içe/dışa aktarma. Sistemde olmayan yeni mevzuatlar Excel'e otomatik sütun olarak eklenir.
7. **Yedekleme Sistemi:** `spatie/laravel-backup` entegre edilmiştir. Admin panelinden (Yedekler sayfası) tek tıkla yedek alınabilir, indirilebilir ve silinebilir. `BackupController.php` ile yönetilir.
8. **Güvenlik:** Yetkisiz tesis erişimleri ve admin sayfası erişimleri engellenmiştir.

## Temiz Kurulum (Yönetici Hesabı)

GitHub kaynak arşivi uygulama veritabanını içermez. Yeni kurulumda tesisler, ziyaretler, kullanıcılar, ayarlar ve loglar boş başlar. Projeyi indirdikten sonra `.env.example` dosyasını `.env` olarak kopyalayın; ilk yönetici hesabını oluşturmak için `.env` içindeki `INITIAL_ADMIN_USERNAME`, `INITIAL_ADMIN_NAME` ve en az 16 karakterli `INITIAL_ADMIN_PASSWORD` değerlerini doldurun. İsteğe bağlı olarak `INITIAL_ADMIN_EMAIL` ekleyin. Parolayı `.env.example` veya başka bir kaynak kodu dosyasına yazmayın.

PowerShell'de SQLite dosyasını oluşturun, uygulama anahtarını üretin, boş veritabanını hazırlayın ve yalnızca ilk yönetici hesabını ekleyin:

```powershell
composer install
Copy-Item .env.example .env
if (!(Test-Path database\database.sqlite)) { New-Item database\database.sqlite -ItemType File }
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
```

Seeder, örnek/test hesabı oluşturmaz. Aynı yönetici kullanıcı adı zaten aktif bir yöneticiye aitse hesabın parolasını veya diğer bilgilerini değiştirmeden tekrar çalışır; başka bir kullanıcıya aitse işlemi açık hata ile durdurur. Mevcut veritabanını boşaltmak için `migrate:fresh` kullanmayın; bu komut tüm tabloları siler.