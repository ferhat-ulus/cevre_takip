<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $sessionVersion = $request->session()->get('auth_session_version');

        if (!$user || !$user->active || $sessionVersion !== (int) $user->sess_ver) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Oturumunuz güvenlik nedeniyle sonlandırıldı. Lütfen yeniden giriş yapın.',
                    'redirect' => route('login'),
                ], Response::HTTP_UNAUTHORIZED);
            }

            return redirect()->route('login')->with('error', 'Oturumunuz güvenlik nedeniyle sonlandırıldı. Lütfen yeniden giriş yapın.');
        }

        return $next($request);
    }
}
