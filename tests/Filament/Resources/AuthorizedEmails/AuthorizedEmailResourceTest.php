<?php

namespace Tests\Filament\Resources\AuthorizedEmails;

use App\Filament\Resources\AuthorizedEmails\AuthorizedEmailResource;
use App\Models\User;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AuthorizedEmailResource::class)]
class AuthorizedEmailResourceTest extends TestCase
{
    public static function roleProvider(): Generator
    {
        yield 'admin' => [true, 200];
        yield 'editor' => [false, 403];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function it_restricts_the_allowlist_page_to_admins(bool $isAdmin, int $expectedStatus): void
    {
        $user = $isAdmin ? User::factory()->admin()->create() : User::factory()->create();

        $response = $this->actingAs($user)->get(AuthorizedEmailResource::getUrl('index'));

        $response->assertStatus($expectedStatus);
    }
}
