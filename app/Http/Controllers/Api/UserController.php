<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog; // YENİ EKLENDİ
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Bu sayfaya erişim yetkiniz yok.');
        }

        $users = User::orderBy('role', 'desc')->orderBy('name')->get();
        
        return Inertia::render('Kullanicilar', [
            'users' => $users,
            'isAdmin' => true
        ]);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username|alpha_dash',
            'email' => 'nullable|email|max:255',
            'role' => 'required|in:admin,employee',
            'password' => 'nullable|string|min:6'
        ]);

        $tempPassword = $request->password ?: Str::random(10);

        $user = User::forceCreate([
            'name' => mb_strtoupper($request->name, 'UTF-8'),
            'username' => strtolower($request->username),
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($tempPassword),
            'active' => 1,
            'must_change' => $request->password ? 0 : 1,
            'sess_ver' => 1,
            'cal_token' => Str::random(32)
        ]);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user_created',
            'description' => "Yeni kullanıcı eklendi: {$user->name} ({$user->role})",
            'ip_address' => $request->ip()
        ]);

        return response()->json(['ok' => true, 'password' => $tempPassword, 'user' => $user]);
    }

    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Yetkisiz işlem'], 403);
        }

        $user = User::findOrFail($id);
        $patch = $request->all();
        $response = ['ok' => true];
        $logDetails = [];

        if (isset($patch['email']) && $patch['email'] !== $user->email) {
            $user->email = $patch['email'];
            $logDetails[] = "E-posta güncellendi";
        }

        if (isset($patch['role']) && in_array($patch['role'], ['admin', 'employee']) && $user->role !== $patch['role']) {
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->where('active', true)->count();
                if ($adminCount <= 1) {
                    return response()->json(['error' => 'Son yöneticinin rolü değiştirilemez.'], 422);
                }
            }
            $logDetails[] = "Rol değiştirildi: {$user->role} -> {$patch['role']}";
            $user->role = $patch['role'];
            $user->sess_ver += 1; 
        }

        // DURUM DEĞİŞİKLİĞİ VE LOGLAMA
        if (isset($patch['active']) && (bool)$patch['active'] !== (bool)$user->active) {
            if ($user->role === 'admin' && $user->active) {
                $adminCount = User::where('role', 'admin')->where('active', true)->count();
                if ($adminCount <= 1) {
                    return response()->json(['error' => 'Son yönetici pasif yapılamaz.'], 422);
                }
            }
            $user->active = (bool)$patch['active'];
            $user->sess_ver += 1;
            
            $statusText = $user->active ? 'Aktif' : 'Pasif (İşten Ayrıldı / Arşiv)';
            $logDetails[] = "Durum değiştirildi: {$statusText}";
            
            // SİSTEM LOGLARINA KAYIT
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'user_status_changed',
                'description' => "{$user->name} adlı kullanıcının durumu '{$statusText}' olarak değiştirildi.",
                'ip_address' => $request->ip()
            ]);
        }

        if (isset($patch['resetPassword']) && $patch['resetPassword'] === true) {
            $newPassword = !empty($patch['new_password']) ? $patch['new_password'] : Str::random(10);
            $user->password = Hash::make($newPassword);
            $user->must_change = empty($patch['new_password']) ? 1 : 0;
            $user->sess_ver += 1;
            $response['password'] = $newPassword;
            $logDetails[] = "Şifre sıfırlandı";
            
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'user_password_reset',
                'description' => "{$user->name} adlı kullanıcının şifresi sıfırlandı.",
                'ip_address' => $request->ip()
            ]);
        }

        $user->save();

        if (count($logDetails) > 0 && !isset($patch['resetPassword'])) {
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => 'user_updated',
                'description' => "{$user->name} kullanıcısında değişiklik yapıldı: " . implode(', ', $logDetails),
                'ip_address' => $request->ip()
            ]);
        }

        return response()->json($response);
    }
}