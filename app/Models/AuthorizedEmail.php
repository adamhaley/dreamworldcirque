<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\AuthorizedEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['email', 'role', 'is_active'])]
class AuthorizedEmail extends Model
{
    /** @use HasFactory<AuthorizedEmailFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AuthorizedEmail $authorizedEmail): void {
            $authorizedEmail->email = Str::lower(trim($authorizedEmail->email));
        });

        // Revoking or re-roling an allowlist entry must also apply to password logins and live sessions.
        static::saved(function (AuthorizedEmail $authorizedEmail): void {
            User::query()->where('email', $authorizedEmail->email)->update([
                'role' => $authorizedEmail->role,
                'is_active' => $authorizedEmail->is_active,
            ]);
        });

        static::deleted(function (AuthorizedEmail $authorizedEmail): void {
            User::query()->where('email', $authorizedEmail->email)->update(['is_active' => false]);
        });
    }
}
