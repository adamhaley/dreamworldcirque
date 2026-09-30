<?php

namespace Tests\Http\Controllers;

use App\Enums\PageTemplate;
use App\Http\Controllers\PageController;
use App\Models\FaqItem;
use App\Models\GalleryPhoto;
use App\Models\Page;
use App\Models\Quote;
use App\Models\SiteSetting;
use App\Models\Spotlight;
use App\Models\User;
use Generator;
use Illuminate\Support\Facades\Storage;
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

        $response
            ->assertOk()
            ->assertSee('Absolutely magical')
            ->assertSee('Silks at sunset')
            ->assertSee('<a class="carousel-slide-link" href="/gallery/" aria-label="View full gallery">', escape: false);
    }

    #[Test]
    #[DataProvider('plainTextBodyTemplates')]
    public function it_preserves_page_body_line_breaks_and_escapes_html(PageTemplate $template): void
    {
        Page::factory()->template($template)->create([
            'slug' => 'example',
            'section_heading' => 'Our performances',
            'body' => "First & second\nNext line\r\n\r\n<script>alert(1)</script>",
        ]);

        $response = $this->get('/example/');

        $response->assertOk()
            ->assertSee("First &amp; second<br />\nNext line<br />\r\n<br />\r\n&lt;script&gt;alert(1)&lt;/script&gt;", escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    public static function plainTextBodyTemplates(): Generator
    {
        yield 'standard' => [PageTemplate::Standard];
        yield 'service' => [PageTemplate::Service];
        yield 'services index' => [PageTemplate::ServicesIndex];
        yield 'gallery' => [PageTemplate::Gallery];
        yield 'contact' => [PageTemplate::Contact];
    }

    #[Test]
    public function it_preserves_line_breaks_and_escapes_html_in_homepage_content(): void
    {
        Page::factory()->home()->create();
        Spotlight::factory()->create(['body' => "Spotlight\n<script>alert(1)</script>"]);
        Quote::factory()->create(['body' => "Quote\r\n<script>alert(1)</script>"]);
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);
        Page::factory()->template(PageTemplate::Service)->create([
            'parent_id' => $services->id,
            'card_excerpt' => "Service\n\n<script>alert(1)</script>",
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee("Spotlight<br />\n&lt;script&gt;alert(1)&lt;/script&gt;", escape: false)
            ->assertSee("Quote<br />\r\n&lt;script&gt;alert(1)&lt;/script&gt;", escape: false)
            ->assertSee("Service<br />\n<br />\n&lt;script&gt;alert(1)&lt;/script&gt;", escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    }

    #[Test]
    #[DataProvider('ctaTemplates')]
    public function it_includes_the_shared_quote_form_beneath_subpage_ctas(PageTemplate $template): void
    {
        Page::factory()->template($template)->create([
            'slug' => 'example',
            'cta_heading' => 'Plan your event',
            'cta_button_label' => 'Get in touch',
        ]);

        $response = $this->get('/example/');

        $response->assertOk()
            ->assertSee('Get in touch')
            ->assertSee('data-contact-reveal', escape: false)
            ->assertSee('aria-expanded="false" aria-controls="cta-contact-form"', escape: false)
            ->assertSee('<div id="cta-contact-form" class="scroll-target" hidden>', escape: false)
            ->assertSee('name="form_type" value="Get_A_Quote Form Submission"', escape: false)
            ->assertSee('class="surface-card contact-form" action="'.route('contact.store').'"', escape: false);
    }

    public static function ctaTemplates(): Generator
    {
        yield 'standard' => [PageTemplate::Standard];
        yield 'service' => [PageTemplate::Service];
        yield 'services index' => [PageTemplate::ServicesIndex];
        yield 'gallery' => [PageTemplate::Gallery];
        yield 'faq' => [PageTemplate::Faq];
    }

    #[Test]
    public function it_omits_the_cta_form_when_the_page_has_no_cta(): void
    {
        Page::factory()->template(PageTemplate::Standard)->create([
            'slug' => 'example',
            'cta_heading' => null,
        ]);

        $this->get('/example/')
            ->assertOk()
            ->assertDontSee('data-contact-reveal', escape: false)
            ->assertDontSee('class="surface-card contact-form"', escape: false);
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
    public function it_renders_a_home_link_in_the_masthead(): void
    {
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);
        Page::factory()->template(PageTemplate::Service)->create([
            'parent_id' => $services->id, 'slug' => 'aerial',
        ]);

        $response = $this->get('/services/aerial/');

        $response->assertSee(
            '<a class="hero-back-link" href="/" aria-label="Back to home">',
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
    public function it_shows_the_card_image_on_the_services_index_when_set(): void
    {
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);
        Page::factory()->template(PageTemplate::Service)->create([
            'parent_id' => $services->id, 'card_image' => 'pages/card.jpg', 'hero_image' => 'pages/hero.jpg',
        ]);

        $response = $this->get('/services/');

        $response->assertSee('<img src="/storage/pages/card.jpg"', escape: false);
    }

    #[Test]
    public function it_falls_back_to_the_hero_image_for_the_card_when_no_card_image_is_set(): void
    {
        $services = Page::factory()->template(PageTemplate::ServicesIndex)->create(['slug' => 'services']);
        Page::factory()->template(PageTemplate::Service)->create([
            'parent_id' => $services->id, 'card_image' => null, 'hero_image' => 'pages/hero.jpg',
        ]);

        $response = $this->get('/services/');

        $response->assertSee('<img src="/storage/pages/hero.jpg"', escape: false);
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

    #[Test]
    public function it_prefers_the_dedicated_social_sharing_image_when_set(): void
    {
        Page::factory()->home()->create(['og_image' => 'pages/share.jpg', 'hero_image' => 'pages/hero.jpg']);

        $response = $this->get('/');

        $response->assertSee('<meta property="og:image" content="'.asset('storage/pages/share.jpg').'" />', escape: false);
    }

    #[Test]
    public function it_falls_back_to_the_generated_composite_when_no_social_sharing_image_is_set(): void
    {
        Storage::fake('public');
        $gdImage = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($gdImage);
        Storage::disk('public')->put('pages/hero.jpg', ob_get_clean());
        imagedestroy($gdImage);
        $page = Page::factory()->create(['slug' => 'example', 'hero_image' => 'pages/hero.jpg']);
        $generatedPath = Page::ogImageStoragePathFor($page->id);

        $response = $this->get('/example/');

        $response->assertSee('<meta property="og:image" content="'.asset('storage/'.$generatedPath).'" />', escape: false);
    }

    #[Test]
    public function it_falls_back_to_the_hero_image_when_neither_a_social_sharing_image_nor_a_generated_composite_exists(): void
    {
        Page::factory()->create(['slug' => 'example', 'hero_image' => 'pages/hero.jpg']);

        $response = $this->get('/example/');

        $response->assertSee('<meta property="og:image" content="'.asset('storage/pages/hero.jpg').'" />', escape: false);
    }

    #[Test]
    public function it_falls_back_to_the_site_default_image_when_neither_is_set(): void
    {
        Page::factory()->home()->create();

        $response = $this->get('/');

        $response->assertSee('<meta property="og:image" content="'.asset('assets/video/hero-poster.jpg').'" />', escape: false);
    }

    #[Test]
    public function it_declares_the_real_og_image_dimensions_so_crawlers_do_not_have_to_infer_them(): void
    {
        Storage::fake('public');
        $gdImage = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($gdImage);
        Storage::disk('public')->put('pages/hero.jpg', ob_get_clean());
        imagedestroy($gdImage);
        Page::factory()->create(['slug' => 'example', 'hero_image' => 'pages/hero.jpg']);

        $response = $this->get('/example/');

        $response->assertSee('<meta property="og:image:width" content="1200" />', escape: false);
        $response->assertSee('<meta property="og:image:height" content="630" />', escape: false);
    }

    #[Test]
    public function it_declares_the_site_default_image_dimensions(): void
    {
        Page::factory()->home()->create();

        $response = $this->get('/');

        $response->assertSee('<meta property="og:image:width" content="1280" />', escape: false);
        $response->assertSee('<meta property="og:image:height" content="720" />', escape: false);
    }

    #[Test]
    public function it_includes_the_ga4_snippet_on_the_production_host_when_configured(): void
    {
        config(['app.url' => 'https://dreamworldcirque.com', 'site.google_analytics_id' => 'G-TEST12345']);
        Page::factory()->home()->create();

        $response = $this->get('https://dreamworldcirque.com/');

        $response->assertSee('googletagmanager.com/gtag/js?id=G-TEST12345', escape: false);
        $response->assertSee("gtag('config', 'G-TEST12345');", escape: false);
    }

    #[Test]
    public function it_omits_the_ga4_snippet_on_a_non_production_host(): void
    {
        config(['app.url' => 'https://dreamworldcirque.com', 'site.google_analytics_id' => 'G-TEST12345']);
        Page::factory()->home()->create();

        $response = $this->get('https://dreamworldcirque.ahmedia.ai/');

        $response->assertDontSee('googletagmanager.com', escape: false);
    }

    #[Test]
    public function it_omits_the_ga4_snippet_when_no_measurement_id_is_configured(): void
    {
        config(['app.url' => 'https://dreamworldcirque.com', 'site.google_analytics_id' => null]);
        Page::factory()->home()->create();

        $response = $this->get('https://dreamworldcirque.com/');

        $response->assertDontSee('googletagmanager.com', escape: false);
    }

    #[Test]
    public function it_overrides_the_accent_color_when_one_is_set(): void
    {
        Page::factory()->home()->create();
        SiteSetting::current()->update(['accent_color' => '#3366ff']);

        $response = $this->get('/');

        $response->assertSee('<style>:root { --color-accent: #3366ff; }</style>', escape: false);
    }

    #[Test]
    public function it_omits_the_accent_color_override_by_default(): void
    {
        Page::factory()->home()->create();

        $response = $this->get('/');

        $response->assertDontSee('--color-accent:', escape: false);
    }
}
