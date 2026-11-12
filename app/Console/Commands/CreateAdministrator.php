<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdministrator extends Command
{
    protected $signature = 'app:create-admin
                            {username? : Giriş için kullanılacak kısa kullanıcı adı}
                            {--name= : Ad soyad}
                            {--email= : E-posta adresi}';

    protected $description = 'İlk sistem yöneticisi hesabını güvenli şekilde oluşturur.';

    public function handle(): int
    {
        $username = (string) ($this->argument('username') ?: $this->ask('Kullanıcı adı'));
        $name = (string) ($this->option('name') ?: $this->ask('Ad soyad'));
        $email = $this->option('email') ?: $this->ask('E-posta adresi (isteğe bağlı)');

        $validator = Validator::make([
            'username' => $username,
            'name' => $name,
            'email' => $email ?: null,
        ], [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username'],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = (string) $this->secret('Geçici parola (en az 12 karakter)');
        $confirmation = (string) $this->secret('Geçici parola tekrar');

        if (mb_strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Parola en az 12 karakter olmalı ve iki giriş aynı olmalıdır.');

            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'name' => $name,
            'email' => $email ?: null,
            'password' => Hash::make($password),
            'role' => 'admin',
            'active' => true,
            'must_change' => true,
            'sess_ver' => 1,
        ]);

        $this->info('Sistem yöneticisi oluşturuldu. İlk girişte parolasını değiştirmesi istenecektir.');

        return self::SUCCESS;
    }
}
