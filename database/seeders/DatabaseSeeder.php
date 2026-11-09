<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use InvalidArgumentException;
use LogicException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = config('initial-admin');
        $username = strtolower(trim((string) $admin['username']));
        $name = trim((string) $admin['name']);
        $email = trim((string) ($admin['email'] ?? ''));
        $password = (string) $admin['password'];

        if (! preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
            throw new InvalidArgumentException('INITIAL_ADMIN_USERNAME 3-50 karakter olmalı; harf, sayı, tire ve alt çizgi içerebilir.');
        }

        if ($name === '' || mb_strlen($name) > 255) {
            throw new InvalidArgumentException('INITIAL_ADMIN_NAME boş olamaz ve 255 karakteri aşamaz.');
        }

        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255)) {
            throw new InvalidArgumentException('INITIAL_ADMIN_EMAIL geçerli bir e-posta adresi olmalıdır.');
        }

        if (mb_strlen($password) < 16) {
            throw new InvalidArgumentException('INITIAL_ADMIN_PASSWORD en az 16 karakter olmalıdır.');
        }

        $user = User::firstOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'role' => 'admin',
                'password' => $password,
                'active' => true,
                'must_change' => false,
                'sess_ver' => 1,
            ],
        );

        if ($user->role !== 'admin' || ! $user->active) {
            throw new LogicException('INITIAL_ADMIN_USERNAME mevcut aktif bir yönetici hesabına ait olmalıdır.');
        }

        if ($user->wasRecentlyCreated) {
            $this->command?->info("İlk yönetici hesabı oluşturuldu: {$username}");
        } else {
            $this->command?->info("Yönetici hesabı zaten mevcut; bilgiler değiştirilmedi: {$username}");
        }
    }
}
