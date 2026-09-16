<?php

namespace Tests\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\AuthorizedEmail;
use App\Models\User;
use App\Services\AuthorizedUserResolver;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(GoogleAuthController::class)]
#[CoversClass(AuthorizedUserResolver::class)]
class GoogleAuthControllerTest extends TestCase
{
    #[Test]
    public function it_logs_in_an_allowlisted_google_account(): void
    {
        AuthorizedEmail::factory()->create(['email' => 'jena@example.com', 'role' => UserRole::Editor]);
        Socialite::fake('google', SocialiteUser::fake(['id' => 'google-123', 'name' => 'Jena', 'email' => 'Jena@Example.com']));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs(User::query()->where('email', 'jena@example.com')->sole());
    }

    #[Test]
    public function it_copies_the_allowlist_role_onto_the_user(): void
    {
        AuthorizedEmail::factory()->create(['email' => 'adam@example.com', 'role' => UserRole::Admin]);
        Socialite::fake('google', SocialiteUser::fake(['id' => 'google-1', 'email' => 'adam@example.com']));

        $this->get(route('auth.google.callback'));

        $this->assertDatabaseHas(User::class, ['email' => 'adam@example.com', 'role' => UserRole::Admin->value, 'google_id' => 'google-1']);
    }

    #[Test]
    public function it_rejects_an_unlisted_google_account(): void
    {
        Socialite::fake('google', SocialiteUser::fake(['email' => 'outsider@example.com']));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('filament.admin.auth.login'));
        $this->assertGuest();
    }

    #[Test]
    public function it_rejects_an_inactive_allowlist_entry(): void
    {
        AuthorizedEmail::factory()->create(['email' => 'former@example.com', 'is_active' => false]);
        Socialite::fake('google', SocialiteUser::fake(['email' => 'former@example.com']));

        $this->get(route('auth.google.callback'));

        $this->assertGuest();
    }
}
