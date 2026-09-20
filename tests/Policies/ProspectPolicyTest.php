<?php

namespace Tests\Policies;

use App\Models\Prospect;
use App\Models\User;
use App\Policies\ProspectPolicy;
use Generator;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectPolicy::class)]
class ProspectPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public static function roleProvider(): Generator
    {
        yield 'admin' => [true, true];
        yield 'editor' => [false, false];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function it_restricts_prospect_management_to_admins(bool $isAdmin, bool $expected): void
    {
        $user = $isAdmin ? User::factory()->admin()->create() : User::factory()->create();

        $this->assertSame($expected, $user->can('viewAny', Prospect::class));
        $this->assertSame($expected, $user->can('view', Prospect::factory()->create()));
        $this->assertSame($expected, $user->can('update', Prospect::factory()->create()));
    }
}
