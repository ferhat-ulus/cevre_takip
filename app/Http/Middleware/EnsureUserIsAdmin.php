<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Kullanıcı yoksa veya rolü (küçük/büyük harf ve boşluklardan arındırılmış olarak) 'admin' değilse engelle
        if (!$request->user() || strtolower(trim($request->user()->role)) !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz yok. (Sadece sistem yöneticileri girebilir)');
        }

        return $next($request);
    }
}