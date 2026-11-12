<?php

// PHP'nin yerleşik geliştirme sunucusu için yönlendirme dosyası.
// Mevcut CSS/JavaScript/görsel dosyalarını doğrudan verir; diğer
// istekleri Laravel uygulamasına gönderir.
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$publicFile = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($publicFile)) {
    return false;
}

require __DIR__ . '/public/index.php';
