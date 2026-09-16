<?php

namespace Tests\Policies;

use App\Models\AuthorizedEmail;
use App\Models\User;
use App\Policies\AuthorizedEmailPolicy;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AuthorizedEmailPolicy::class)]
class AuthorizedEmailPolicyTest extends TestCase
{
    public static function roleProvider(): Generator
    {
        yield 'admin' => [true, true];
        yield 'editor' => [false, false];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function it_restricts_allowlist_management_to_admins(bool $isAdmin, bool $expected): void
    {
        $user = $isAdmin ? User::factory()->admin()->create() : User::factory()->create();

        $this->assertSame($expected, $user->can('update', AuthorizedEmail::factory()->create()));
    }
}
