<?php

namespace App\Actions;

use App\Models\Page;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;

class GenerateOgImageAction
{
    private const int WIDTH = 1200;

    private const int HEIGHT = 630;

    private const int MARGIN = 60;

    private const string DOMAIN = 'dreamworldcirque.com';

    /**
     * Composite the page's hero image with its own hero heading and
     * subheading (bottom-right, mirroring what a visitor actually lands on)
     * plus an editable "come hither" headline (top-left) and the site
     * domain (top-right) into a 1200x630 social share image. Used as the OG
     * image fallback when no manual override is uploaded. Removes any stale
     * generated file when the page no longer has a hero image to composite
     * against.
     */
    public function __invoke(Page $page): ?string
    {
        $path = Page::ogImageStoragePathFor($page->id);

        if (! $page->hero_image || ! Storage::disk('public')->exists($page->hero_image)) {
            Storage::disk('public')->delete($path);

            return null;
        }

        $heading = $page->hero_heading ?: $page->title;
        $left = self::MARGIN;
        $right = self::WIDTH - self::MARGIN;
        $top = self::MARGIN;
        $bottom = self::HEIGHT - self::MARGIN;

        $image = (new ImageManager(new Driver))
            ->decodePath(Storage::disk('public')->path($page->hero_image))
            ->cover(self::WIDTH, self::HEIGHT)
            ->drawRectangle(function (RectangleFactory $rectangle): void {
                $rectangle->size(self::WIDTH, self::HEIGHT);
                $rectangle->at(0, 0);
                $rectangle->background('#00000073');
            })
            ->text(self::DOMAIN, $right, $top, function (FontFactory $font): void {
                $font->filename(public_path('fonts/Catamaran-Regular.ttf'));
                $font->size(32);
                $font->color('rgba(255, 255, 255, 0.92)');
                $font->align('right', 'top');
            })
            ->text($heading, $right, $bottom - 56, function (FontFactory $font): void {
                $font->filename(public_path('fonts/Catamaran-Bold.ttf'));
                $font->size(58);
                $font->color('#ffffff');
                $font->align('right', 'bottom');
                $font->lineHeight(1.15);
                $font->wrap(900);
            });

        if ($page->hero_subheading) {
            $image->text($page->hero_subheading, $right, $bottom, function (FontFactory $font): void {
                $font->filename(public_path('fonts/Catamaran-Regular.ttf'));
                $font->size(32);
                $font->color('rgba(255, 255, 255, 0.92)');
                $font->align('right', 'bottom');
                $font->lineHeight(1.2);
                $font->wrap(900);
            });
        }

        if ($page->og_cta) {
            $image->text($page->og_cta, $left, $top, function (FontFactory $font): void {
                $font->filename(public_path('fonts/Catamaran-Bold.ttf'));
                $font->size(72);
                $font->color('#ffffff');
                $font->align('left', 'top');
                $font->lineHeight(1.1);
                $font->wrap(600);
            });
        }

        Storage::disk('public')->makeDirectory('og-images');
        $image->save(Storage::disk('public')->path($path), quality: 85);

        return $path;
    }
}
