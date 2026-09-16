<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = Str::lower(trim((string) config('site.seed_admin.email')));
        $password = (string) config('site.seed_admin.password');

        if ($email === '' || $password === '') {
            $this->command?->warn('SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD not set; skipping admin user.');

            return;
        }

        AuthorizedEmail::query()->updateOrCreate(['email' => $email], ['role' => UserRole::Admin, 'is_active' => true]);

        User::query()->firstOrCreate(['email' => $email], [
            'name' => config('site.seed_admin.name') ?: $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
