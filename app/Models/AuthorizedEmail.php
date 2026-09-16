<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\AuthorizedEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
