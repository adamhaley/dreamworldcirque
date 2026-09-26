<?php

namespace Tests\Http\Middleware;

use App\Http\Middleware\RedirectWwwToApex;
use App\Models\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RedirectWwwToApex::class)]
class RedirectWwwToApexTest extends TestCase
{
    #[Test]
    public function it_redirects_the_www_host_to_the_apex_domain_preserving_the_path_and_query(): void
    {
        Page::factory()->create(['slug' => 'about']);

        $response = $this->get('http://www.dreamworldcirque.com/about?ref=test');

        $response->assertRedirect('https://dreamworldcirque.com/about?ref=test')->assertStatus(301);
    }

    #[Test]
    public function it_leaves_the_apex_domain_and_other_hosts_alone(): void
    {
        Page::factory()->home()->create();

        $response = $this->get('/');

        $response->assertOk();
    }
}
