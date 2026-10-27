<?php

namespace Tests\Http\Controllers;

use App\Http\Controllers\FaviconController;
use App\Models\SiteSetting;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(FaviconController::class)]
class FaviconControllerTest extends TestCase
{
    public static function sizeProvider(): Generator
    {
        yield 'browser tab' => [48];
        yield 'apple touch icon' => [180];
        yield 'android icon' => [192];
    }

    #[Test]
    #[DataProvider('sizeProvider')]
    public function it_renders_a_square_png_at_the_requested_size(int $size): void
    {
        $response = $this->get("/favicon-{$size}.png");

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame([$size, $size], array_slice(getimagesizefromstring($response->getContent()), 0, 2));
    }

    #[Test]
    public function it_returns_not_found_for_an_unsupported_size(): void
    {
        $response = $this->get('/favicon-64.png');

        $response->assertNotFound();
    }

    public static function accentColorProvider(): Generator
    {
        yield 'custom accent color' => ['#9500a6', [0x95, 0x00, 0xA6]];
        yield 'default pink when unset' => [null, [0xEE, 0x09, 0x79]];
    }

    /** @param array{int, int, int} $expectedRgb */
    #[Test]
    #[DataProvider('accentColorProvider')]
    public function it_draws_the_lettermark_in_the_accent_color(?string $accentColor, array $expectedRgb): void
    {
        SiteSetting::current()->update(['accent_color' => $accentColor]);

        $response = $this->get('/favicon-192.png');

        $this->assertContains($expectedRgb, $this->colorsIn($response->getContent()));
    }

    /** @return list<array{int, int, int}> */
    private function colorsIn(string $png): array
    {
        $image = imagecreatefromstring($png);
        $colors = [];

        for ($x = 0; $x < imagesx($image); $x++) {
            for ($y = 0; $y < imagesy($image); $y++) {
                $rgb = imagecolorat($image, $x, $y);
                $colors[$rgb] = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
            }
        }

        return array_values($colors);
    }
}
