<?php

namespace Database\Seeders;

use App\Enums\PageTemplate;
use App\Models\GalleryPhoto;
use App\Models\Page;
use Illuminate\Database\Seeder;

class ServicePhotoSeeder extends Seeder
{
    public function run(): void
    {
        $examples = [
            'acts-shows' => ['DWC_CHairs_5__.jpg', 'DWC_Cyr_White.jpg'],
            'ambient-entertainment' => ['DWC_Living_Vine.jpg', 'DWC_Ambient_Stilts_Wings.jpg'],
            'weddings-private-events' => ['DWC_Stilts_Red_Carpet.jpg', 'DWC_Duo_Silks_Red.jpg'],
            'corporate-events' => ['DWC_Cyr_White.jpg', 'DWC_Neon_Noire_Group.jpg'],
            'film-tv-productions' => ['DWC_AFN_Contortion.jpg', 'DWC_Great_Gatsby_Aerial_Moon_1.jpg'],
            'equipment-rentals' => ['DWC_Vegas_LED_Hoops.jpg', 'DWC_Cyr_White.jpg'],
            'costume-rentals' => ['DWC_Neon_Noire_Group.jpg', 'DWC_StiltsRoaring20s.jpg'],
        ];

        foreach ($examples as $slug => $filenames) {
            $page = Page::query()->where('template', PageTemplate::Service)->where('slug', $slug)->first();

            if (! $page || $page->servicePhotos()->exists()) {
                continue;
            }

            foreach ($filenames as $sortOrder => $filename) {
                $photo = GalleryPhoto::query()->where('image', 'gallery/'.$filename)->first();

                if (! $photo) {
                    continue;
                }

                $page->servicePhotos()->create([
                    'image' => $photo->image,
                    'alt' => $photo->alt,
                    'caption' => $photo->caption,
                    'sort_order' => $sortOrder,
                    'is_placeholder' => true,
                ]);
            }
        }
    }
}
