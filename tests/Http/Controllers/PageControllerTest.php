<?php

namespace Tests\Http\Controllers;

use App\Enums\PageTemplate;
use App\Http\Controllers\PageController;
use App\Models\FaqItem;
use App\Models\GalleryPhoto;
use App\Models\Page;
use App\Models\Quote;
use App\Models\User;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PageController::class)]
class PageControllerTest extends TestCase
{
    public static function templateProvider(): Generator
    {
        yield 'standard' => [PageTemplate::Standard];
        yield 'service' => [PageTemplate::Service];
        yield 'services index' => [PageTemplate::ServicesIndex];
        yield 'gallery' => [PageTemplate::Gallery];
        yield 'faq' => [PageTemplate::Faq];
        yield 'contact' => [PageTemplate::Contact];
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function it_renders_each_template(PageTemplate $template): void
    {
        Page::factory()->template($template)->create(['slug' => 'example', 'hero_heading' => 'Example heading']);

        $response = $this->get('/example/');

        $response->assertOk()->assertSee('Example heading');
    }

    #[Test]
    public function it_renders_the_home_page_with_its_homepage_content(): void
    {
        Page::factory()->home()->create(['hero_heading' => 'Dream World Cirque']);
        Quote::factory()->create(['body' => 'Absolutely magical']);
        GalleryPhoto::factory()->inHomepageCarousel()->create(['caption' => 'Silks at sunset']);

        $response = $this->get('/');

        $response->assertOk()->assertSee('Absolutely magical')->assertSee('Silks at sunset');
    }

    #[Test]
    public function it_renders_a_nested_child_page_by_its_full_path(): void
    {
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);
        Page::factory()->template(PageTemplate::Service)->create([
            'parent_id' => $services->id, 'slug' => 'aerial', 'hero_heading' => 'Aerial acts',
        ]);

        $response = $this->get('/services/aerial/');

        $response->assertOk()->assertSee('Aerial acts');
    }

    #[Test]
    public function it_renders_a_history_back_link_with_the_parent_page_as_a_fallback(): void
    {
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);
        Page::factory()->template(PageTemplate::Service)->create([
            'parent_id' => $services->id, 'slug' => 'aerial',
        ]);

        $response = $this->get('/services/aerial/');

        $response->assertSee(
            '<a class="hero-back-link" href="/services/" aria-label="Back to previous page" data-history-back>',
            escape: false,
        );
    }

    #[Test]
    public function it_lists_published_children_on_the_services_index(): void
    {
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);
        Page::factory()->template(PageTemplate::Service)->create(['parent_id' => $services->id, 'title' => 'Visible Service']);
        Page::factory()->template(PageTemplate::Service)->unpublished()->create(['parent_id' => $services->id, 'title' => 'Draft Service']);

        $response = $this->get('/services/');

        $response->assertSee('Visible Service')->assertDontSee('Draft Service');
    }

    #[Test]
    public function it_hides_unpublished_pages_from_guests(): void
    {
        Page::factory()->unpublished()->create(['slug' => 'draft']);

        $response = $this->get('/draft/');

        $response->assertNotFound();
    }

    #[Test]
    public function it_shows_unpublished_pages_to_logged_in_users(): void
    {
        Page::factory()->unpublished()->create(['slug' => 'draft', 'hero_heading' => 'Draft heading']);

        $response = $this->actingAs(User::factory()->create())->get('/draft/');

        $response->assertOk()->assertSee('Draft heading');
    }

    #[Test]
    public function it_returns_not_found_for_an_unknown_path(): void
    {
        $response = $this->get('/does-not-exist/');

        $response->assertNotFound();
    }

    #[Test]
    public function it_renders_faq_answers_as_html(): void
    {
        Page::factory()->template(PageTemplate::Faq)->create(['slug' => 'faq']);
        FaqItem::factory()->create(['answer' => '<p>We travel <strong>worldwide</strong>.</p>']);

        $response = $this->get('/faq/');

        $response->assertSee('<strong>worldwide</strong>', escape: false);
    }

    public static function noindexProvider(): Generator
    {
        yield 'noindex enabled' => [true, true];
        yield 'noindex disabled' => [false, false];
    }

    #[Test]
    #[DataProvider('noindexProvider')]
    public function it_sends_the_noindex_header_only_when_configured(bool $noindex, bool $expected): void
    {
        config(['site.noindex' => $noindex]);
        Page::factory()->home()->create();

        $response = $this->get('/');

        $this->assertSame($expected, $response->headers->has('X-Robots-Tag'));
    }
}
