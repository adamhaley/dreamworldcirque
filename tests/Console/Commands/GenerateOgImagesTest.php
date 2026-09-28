<?php

namespace Tests\Console\Commands;

use App\Console\Commands\GenerateOgImages;
use App\Models\Page;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(GenerateOgImages::class)]
class GenerateOgImagesTest extends TestCase
{
    #[Test]
    public function it_generates_og_images_for_every_page_with_a_hero_image(): void
    {
        Storage::fake('public');
        $gdImage = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($gdImage);
        Storage::disk('public')->put('pages/hero.jpg', ob_get_clean());
        imagedestroy($gdImage);
        $withHero = Page::factory()->create(['hero_image' => 'pages/hero.jpg']);
        $withoutHero = Page::factory()->create(['hero_image' => null]);

        $this->artisan('pages:generate-og-images')
            ->expectsOutputToContain('Generated 1 of 1 OG image(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertExists(Page::ogImageStoragePathFor($withHero->id));
        Storage::disk('public')->assertMissing(Page::ogImageStoragePathFor($withoutHero->id));
    }

    #[Test]
    public function it_reports_when_there_are_no_pages_with_a_hero_image(): void
    {
        Page::factory()->create(['hero_image' => null]);

        $this->artisan('pages:generate-og-images')
            ->expectsOutputToContain('No pages with a hero image.')
            ->assertSuccessful();
    }
}
