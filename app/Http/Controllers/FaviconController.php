<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Response;

class FaviconController extends Controller
{
    /** Drawn large, then downsampled, so small sizes get smooth edges. */
    private const CanvasSize = 512;

    /** The box the lettermark is fitted into, leaving a margin on the canvas. */
    private const TextMaxWidth = 460;

    private const TextMaxHeight = 340;

    /**
     * Draws the "DWC" lettermark in the CMS accent color, so the browser-tab
     * icon follows whatever color the client picks in Site Settings. The
     * site layout links here with the color as a ?v= cache-buster, which is
     * what makes the long immutable cache below safe.
     */
    public function __invoke(int $size): Response
    {
        $png = $this->render(SiteSetting::current()->accentColor(), $size);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function render(string $hexColor, int $size): string
    {
        $font = public_path('assets/fonts/Birmingham.ttf');
        [$red, $green, $blue] = sscanf($hexColor, '#%02x%02x%02x');

        $canvas = imagecreatetruecolor(self::CanvasSize, self::CanvasSize);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        $referencePoints = 100;
        [$left, $bottom, , , $right, $top] = imagettfbbox($referencePoints, 0, $font, 'DWC');
        $scale = min(self::TextMaxWidth / ($right - $left), self::TextMaxHeight / ($bottom - $top));
        $points = $referencePoints * $scale;
        $x = (self::CanvasSize - ($right - $left) * $scale) / 2 - $left * $scale;
        $y = (self::CanvasSize - ($bottom - $top) * $scale) / 2 - $top * $scale;
        imagettftext($canvas, $points, 0, (int) round($x), (int) round($y), imagecolorallocate($canvas, $red, $green, $blue), $font, 'DWC');

        $icon = imagecreatetruecolor($size, $size);
        imagecopyresampled($icon, $canvas, 0, 0, 0, 0, $size, $size, self::CanvasSize, self::CanvasSize);

        ob_start();
        imagepng($icon);

        return (string) ob_get_clean();
    }
}
