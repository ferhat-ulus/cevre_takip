<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $key = strtolower($request->username).'|'.$request->ip();

        // 6 hatalı giriş sınırı (Rate Limiting)
        if (RateLimiter::tooManyAttempts($key, 6)) {
            return response()->json(['error' => 'Çok fazla hatalı deneme. 10 dakika sonra tekrar deneyin.'], 429);
        }

        if (Auth::attempt(['username' => $request->username, 'password' => $request->password, 'active' => 1])) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            $user = Auth::user();

            return response()->json([
                'me' => [
                    'id' => $user->id, 'username' => $user->username, 'name' => $user->name,
                    'email' => $user->email, 'role' => $user->role, 'active' => (bool) $user->active,
                    'mustChange' => (bool) $user->must_change,
                ],
            ]);
        }

        RateLimiter::hit($key, 600); // 10 dakika kilitle

        return response()->json(['error' => 'Kullanıcı adı veya şifre hatalı'], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // DÜZELTME: JSON yerine Login sayfasına yönlendiriyoruz
        return redirect('/login');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['me' => null]);
        }

        return response()->json([
            'me' => [
                'id' => $user->id, 'username' => $user->username, 'name' => $user->name,
                'email' => $user->email, 'role' => $user->role, 'active' => (bool) $user->active,
                'mustChange' => (bool) $user->must_change,
            ],
            'calToken' => $user->cal_token,
        ]);
    }
}