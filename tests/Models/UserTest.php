<?php

namespace Tests\Models;

use App\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(User::class)]
class UserTest extends TestCase
{
    #[Test]
    public function it_lets_active_users_into_the_admin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    #[Test]
    public function it_keeps_inactive_users_out_of_the_admin(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }
}
