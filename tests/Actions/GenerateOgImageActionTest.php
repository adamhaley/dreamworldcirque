<?php

namespace Tests\Actions;

use App\Actions\GenerateOgImageAction;
use App\Models\Page;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(GenerateOgImageAction::class)]
class GenerateOgImageActionTest extends TestCase
{
    private function fakeHeroImage(string $path): void
    {
        $gdImage = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($gdImage);
        $contents = ob_get_clean();
        imagedestroy($gdImage);

        Storage::disk('public')->put($path, $contents);
    }

    #[Test]
    public function it_does_nothing_when_the_page_has_no_hero_image(): void
    {
        Storage::fake('public');
        $page = Page::factory()->create(['hero_image' => null]);

        $path = (new GenerateOgImageAction)($page);

        $this->assertNull($path);
        Storage::disk('public')->assertMissing(Page::ogImageStoragePathFor($page->id));
    }

    #[Test]
    public function it_composites_the_hero_image_and_heading_into_a_stored_jpeg(): void
    {
        Storage::fake('public');
        $this->fakeHeroImage('pages/hero.jpg');
        $page = Page::factory()->create([
            'hero_image' => 'pages/hero.jpg',
            'hero_heading' => 'Book your event',
            'hero_subheading' => 'A boutique aerial and cirque company',
        ]);

        $path = (new GenerateOgImageAction)($page);

        $this->assertSame(Page::ogImageStoragePathFor($page->id), $path);
        Storage::disk('public')->assertExists($path);
        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1200, 630], [$size[0], $size[1]]);
    }

    #[Test]
    public function it_prefers_the_og_cta_headline_over_the_cta_heading_when_both_are_set(): void
    {
        Storage::fake('public');
        $this->fakeHeroImage('pages/hero.jpg');
        $page = Page::factory()->create([
            'hero_image' => 'pages/hero.jpg',
            'og_cta' => 'Book Your Signature Moment',
            'cta_heading' => "Let's design your event's signature moment.",
        ]);

        $path = (new GenerateOgImageAction)($page);

        Storage::disk('public')->assertExists($path);
        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1200, 630], [$size[0], $size[1]]);
    }

    #[Test]
    public function it_falls_back_to_the_cta_heading_as_the_come_hither_line_when_og_cta_is_not_set(): void
    {
        Storage::fake('public');
        $this->fakeHeroImage('pages/hero.jpg');
        $page = Page::factory()->create([
            'hero_image' => 'pages/hero.jpg',
            'og_cta' => null,
            'cta_heading' => "Let's design your event's signature moment.",
        ]);

        $path = (new GenerateOgImageAction)($page);

        Storage::disk('public')->assertExists($path);
        $size = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1200, 630], [$size[0], $size[1]]);
    }

    #[Test]
    public function it_deletes_a_previously_generated_image_when_the_hero_image_is_removed(): void
    {
        Storage::fake('public');
        $this->fakeHeroImage('pages/hero.jpg');
        $page = Page::factory()->create(['hero_image' => 'pages/hero.jpg']);
        $path = Page::ogImageStoragePathFor($page->id);
        Storage::disk('public')->assertExists($path);

        $page->hero_image = null;
        (new GenerateOgImageAction)($page);

        Storage::disk('public')->assertMissing($path);
    }
}
