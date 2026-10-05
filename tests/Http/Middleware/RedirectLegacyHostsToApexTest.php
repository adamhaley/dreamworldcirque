<?php

namespace Tests\Http\Middleware;

use App\Http\Middleware\RedirectLegacyHostsToApex;
use App\Models\Page;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RedirectLegacyHostsToApex::class)]
class RedirectLegacyHostsToApexTest extends TestCase
{
    public static function legacyHostProvider(): Generator
    {
        yield 'www on the real domain' => ['http://www.dreamworldcirque.com/about?ref=test'];
        yield 'www on the misspelled domain' => ['http://www.dreamworldcircus.com/about?ref=test'];
    }

    #[Test]
    #[DataProvider('legacyHostProvider')]
    public function it_redirects_legacy_hosts_to_the_apex_domain_preserving_the_path_and_query(string $url): void
    {
        Page::factory()->create(['slug' => 'about']);

        $response = $this->get($url);

        $response->assertRedirect('https://dreamworldcirque.com/about?ref=test')->assertStatus(301);
    }

    #[Test]
    public function it_leaves_the_apex_domain_and_other_hosts_alone(): void
    {
        Page::factory()->home()->create();

        $response = $this->get('/');

        $response->assertOk();
    }

    #[Test]
    public function it_serves_the_bare_misspelled_domain_directly_instead_of_redirecting(): void
    {
        Page::factory()->create(['slug' => 'about']);

        $response = $this->get('http://dreamworldcircus.com/about');

        $response->assertOk();
    }
}
