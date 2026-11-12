<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\ActivityLog;

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
            $request->session()->forget('active_module');

            $user = Auth::user();
            $request->session()->put('auth_session_version', (int) $user->sess_ver);

            return response()->json([
                'me' => [
                    'id' => $user->id, 'username' => $user->username, 'name' => $user->name,
                    'email' => $user->email, 'role' => strtolower($user->role), 'active' => (bool) $user->active,
                    'mustChange' => (bool) $user->must_change,
                ],
                'redirect' => $user->must_change ? '/sifre-degistir' : '/moduller',
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

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->must_change = false;
        $user->sess_ver += 1;
        $user->save();

        $request->session()->put('auth_session_version', (int) $user->sess_ver);
        $request->session()->forget('active_module');

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'password_changed',
            'description' => 'Kullanıcı zorunlu ilk şifre değişikliğini tamamladı.',
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'redirect' => '/moduller']);
    }
}
