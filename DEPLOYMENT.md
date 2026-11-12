# Canlı Kurulum Rehberi

Bu rehber, uygulamanın SSH erişimli bir Linux VDS/XCloud sunucusuna ve MySQL/MariaDB veritabanına kurulması içindir. Paylaşımlı hosting paketi Laravel, Composer ve SSH sağlamıyorsa bu kurulum yöntemi uygulanamaz.

## Sunucu ön koşulları

- PHP 8.3 veya üstü (CLI ile aynı sürüm)
- PHP eklentileri: `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd`, `intl`
- Composer 2, Node.js 20 LTS veya üstü ve NPM
- MySQL 8 / MariaDB 10.6 veya üstü
- Nginx veya Apache; sitenin web kökü yalnızca `public` klasörü olmalıdır
- Cron erişimi
- TLS sertifikası (Let's Encrypt veya eşdeğeri)
- Yedek için `mysqldump`, yeterli disk alanı ve sunucu dışı saklama alanı

## İlk kurulum

1. Sunucuda site için yalnızca yetkili bir kullanıcı kullanın; uygulamayı `public_html` içine değil, örneğin `/var/www/ulus-cevre-takip` klasörüne koyun.
2. Depoyu bu klasöre alın ve bağımlılıkları yükleyin:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader
   npm ci
   npm run build
   ```

3. `.env.production.example` dosyasını `.env` olarak kopyalayın. Şu alanları gerçek değerlerle doldurun: `APP_KEY`, `APP_URL`, tüm `DB_*` değerleri, e-posta ayarları, `BACKUP_PASSWORD` ve `BACKUP_NOTIFICATION_EMAIL`.
4. Uygulama anahtarını yalnızca yeni bir kurulumda üretin:

   ```bash
   php artisan key:generate --force
   ```

5. Şemayı oluşturun ve ilk yöneticiyi ekleyin. Canlı ortamda `--seed` kullanmayın:

   ```bash
   php artisan migrate --force
   php artisan app:create-admin
   ```

6. Yazılabilir klasörleri web sunucusu kullanıcısına verin ve sembolik bağlantıyı oluşturun:

   ```bash
   chown -R www-data:www-data storage bootstrap/cache
   chmod -R ug+rwx storage bootstrap/cache
   php artisan storage:link
   ```

7. Önbellekleri üretin:

   ```bash
   php artisan optimize
   ```

## Nginx örneği

`server_name` alanını gerçek alt alanınızla değiştirin. `root` yolu mutlaka `public` ile bitmelidir.

```nginx
server {
    listen 80;
    server_name portal.ornekalanadiniz.com;
    root /var/www/ulus-cevre-takip/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

TLS sertifikası etkinleştirildikten sonra HTTP isteklerini HTTPS adresine yönlendirin. Doğrudan Nginx/Apache kurulumu yapılıyorsa `TRUSTED_PROXIES` boş kalmalıdır. Cloudflare gibi bir ters vekil kullanılacaksa sadece ilgili vekil IP adresleri yazılmalıdır; `*` kullanılmamalıdır.

## Zamanlanmış işler ve kuyruk

Cron'a aşağıdaki tek satırı ekleyin:

```cron
* * * * * cd /var/www/ulus-cevre-takip && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Bu görev günlük 01:30'da eski yedekleri temizler, 02:00'da yedek alır ve 02:30'da yedek sağlığını denetler. `QUEUE_CONNECTION=database` kullanılacaksa kuyruk çalışanı için ayrıca bir systemd veya Supervisor görevi kurulmalıdır. Şu an kuyruk gerektiren yeni bir iş eklenmedikçe bu adım zorunlu değildir.

## Yedekleme

- Yedek dosyası uygulama anahtarını ve ortam dosyasını içermez.
- MySQL yedeğinin alınabilmesi için `mysqldump` sunucuda çalışmalıdır.
- Yerel sunucu yedeği tek başına yeterli değildir. Her gün oluşan arşivi ayrı bir depolama alanına indirin veya güvenli bir harici depolamaya aktarın.
- Canlıya geçmeden önce bir yedeği ayrı bir test veritabanına geri yükleyerek geri dönüş denemesi yapın.

## Canlıya alma kontrolü

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan backup:run --only-db
```

Son olarak HTTPS adresinden giriş, parola değiştirme, tesis ekleme, Excel dışa aktarma ve yedek indirme işlemlerini yönetici hesabıyla kontrol edin. `APP_DEBUG=false` ve `SESSION_SECURE_COOKIE=true` değerleri doğrulanmadan sistemi kullanıma açmayın.
