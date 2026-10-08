<?php

namespace Tests\Http;

use Generator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversNothing]
class LegacyUrlRedirectTest extends TestCase
{
    public static function legacyUrlProvider(): Generator
    {
        yield 'about page' => ['/about-us.html', '/about/'];
        yield 'quote CTA' => ['/get-a-quote.html', '/contact/'];
        yield 'showcase' => ['/showcase.html', '/gallery/'];
        yield 'video' => ['/video.html', '/'];
        yield 'homepage' => ['/index.html', '/'];
        yield 'muse default export' => ['/default.htm', '/'];
    }

    #[Test]
    #[DataProvider('legacyUrlProvider')]
    public function it_redirects_legacy_muse_urls_permanently(string $legacyUrl, string $newUrl): void
    {
        $response = $this->get($legacyUrl);

        $response->assertRedirect($newUrl)->assertStatus(301);
    }
}
