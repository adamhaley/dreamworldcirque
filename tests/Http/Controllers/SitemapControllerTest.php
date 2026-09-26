<?php

namespace Tests\Http\Controllers;

use App\Http\Controllers\SitemapController;
use App\Models\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SitemapController::class)]
class SitemapControllerTest extends TestCase
{
    #[Test]
    public function it_lists_published_pages_with_trailing_slash_urls(): void
    {
        Page::factory()->home()->create();
        Page::factory()->create(['slug' => 'about']);

        $response = $this->get('/sitemap.xml');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<loc>'.rtrim(config('app.url'), '/').'/</loc>', escape: false)
            ->assertSee('<loc>'.rtrim(config('app.url'), '/').'/about/</loc>', escape: false);
    }

    #[Test]
    public function it_omits_unpublished_pages(): void
    {
        Page::factory()->home()->create();
        Page::factory()->unpublished()->create(['slug' => 'draft']);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee('draft');
    }
}
