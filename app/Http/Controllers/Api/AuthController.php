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
                    'email' => $user->email, 'role' => strtolower($user->role), 'active' => (bool) $user->active,
                    'mustChange' => (bool) $user->must_change,
                ],
            ]);
        }

        RateLimiter::hit($key, 600);

        return response()->json(['error' => 'Kullanıcı adı veya şifre hatalı'], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}