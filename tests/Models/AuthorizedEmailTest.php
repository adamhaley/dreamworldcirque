<?php

namespace Tests\Models;

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AuthorizedEmail::class)]
class AuthorizedEmailTest extends TestCase
{
    #[Test]
    public function it_normalises_the_email_to_lowercase(): void
    {
        $authorizedEmail = AuthorizedEmail::factory()->create(['email' => ' Jena@Example.COM ']);

        $this->assertSame('jena@example.com', $authorizedEmail->email);
    }

    #[Test]
    public function it_deactivates_the_matching_user_when_the_entry_is_deactivated(): void
    {
        $user = User::factory()->create(['email' => 'jena@example.com']);
        $authorizedEmail = AuthorizedEmail::factory()->create(['email' => 'jena@example.com']);

        $authorizedEmail->update(['is_active' => false]);

        $this->assertFalse($user->fresh()->is_active);
    }

    #[Test]
    public function it_syncs_a_role_change_to_the_matching_user(): void
    {
        $user = User::factory()->create(['email' => 'jena@example.com']);
        $authorizedEmail = AuthorizedEmail::factory()->create(['email' => 'jena@example.com']);

        $authorizedEmail->update(['role' => UserRole::Admin]);

        $this->assertTrue($user->fresh()->isAdmin());
    }

    #[Test]
    public function it_deactivates_the_matching_user_when_the_entry_is_deleted(): void
    {
        $user = User::factory()->create(['email' => 'jena@example.com']);
        $authorizedEmail = AuthorizedEmail::factory()->create(['email' => 'jena@example.com']);

        $authorizedEmail->delete();

        $this->assertFalse($user->fresh()->is_active);
    }
}
