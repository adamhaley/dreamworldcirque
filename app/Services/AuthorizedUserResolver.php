<?php

namespace App\Services;

use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthorizedUserResolver
{
    public function resolveFromGoogleUser(SocialiteUser $googleUser): ?User
    {
        if (! filled($googleUser->getEmail())) {
            return null;
        }

        $email = Str::lower(trim($googleUser->getEmail()));

        $authorizedEmail = AuthorizedEmail::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($authorizedEmail === null) {
            return null;
        }

        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName() ?: $email,
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'role' => $authorizedEmail->role,
                'is_active' => true,
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ],
        );
    }
}
