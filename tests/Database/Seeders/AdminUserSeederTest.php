<?php

namespace Tests\Database\Seeders;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AdminUserSeeder::class)]
class AdminUserSeederTest extends TestCase
{
    #[Test]
    public function it_creates_an_admin_who_can_log_in_with_a_password(): void
    {
        config(['site.seed_admin' => ['name' => 'Adam', 'email' => 'adam@example.com', 'password' => 'secret-password']]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'adam@example.com')->sole();
        $this->assertTrue($admin->isAdmin() && Hash::check('secret-password', $admin->password));
    }

    #[Test]
    public function it_skips_when_no_admin_credentials_are_configured(): void
    {
        config(['site.seed_admin' => ['name' => null, 'email' => null, 'password' => null]]);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount(User::class, 0);
    }
}
