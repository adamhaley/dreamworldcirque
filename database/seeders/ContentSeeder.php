<?php

namespace Database\Seeders;

use App\Enums\PageTemplate;
use App\Models\FaqItem;
use App\Models\GalleryPhoto;
use App\Models\Page;
use App\Models\Quote;
use App\Models\SiteSetting;
use App\Models\Spotlight;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        if (Page::query()->exists()) {
            $this->command?->warn('Pages already exist; skipping content import so CMS edits are never overwritten.');

            return;
        }

        $content = require __DIR__.'/data/content.php';

        SiteSetting::current()->update([
            'contact_email' => 'info@dreamworldcirque.com',
            'phone' => '(555) 019-2842',
            'studio_address' => 'Los Angeles, California',
            'facebook_url' => 'https://www.facebook.com/DreamWorldCirque/',
            'instagram_url' => 'https://www.instagram.com/dreamworldcirque/',
            'linkedin_url' => 'https://www.linkedin.com/company/dream-world-cirque',
        ]);

        $quotes = collect($content['quotes'])->map(
            fn (array $quote, int $index): Quote => Quote::create([...$quote, 'sort_order' => $index]),
        );

        foreach ($content['spotlights'] as $index => $spotlight) {
            Spotlight::create([
                ...$spotlight,
                'image' => $this->importImage($spotlight['image'], 'spotlights'),
                'sort_order' => $index,
            ]);
        }

        foreach ($content['gallery'] as $index => $photo) {
            GalleryPhoto::create([
                ...$photo,
                'image' => $this->importImage($photo['image'], 'gallery'),
                'sort_order' => $index,
            ]);
        }

        $faqKeywords = require __DIR__.'/data/faq-keywords.php';

        foreach ($content['faq'] as $index => $item) {
            FaqItem::create([
                ...$item,
                'keywords' => $faqKeywords[$item['question']] ?? null,
                'sort_order' => $index,
            ]);
        }

        $this->createPage($content['home'], ['title' => 'Home', 'template' => PageTemplate::Home]);

        $this->createPage($content['pages']['about'], [
            'title' => 'About', 'slug' => 'about', 'template' => PageTemplate::Standard,
            'sort_order' => 0, 'show_in_nav' => true, 'nav_anchor' => '#about', 'quote_id' => $quotes->first()->id,
        ]);

        $services = $this->createPage($content['pages']['services'], [
            'title' => 'Services', 'slug' => 'services', 'template' => PageTemplate::ServicesIndex,
            'sort_order' => 1, 'show_in_nav' => true, 'nav_anchor' => '#services',
        ]);

        foreach (array_values($content['services']) as $index => $service) {
            $this->createPage($service, [
                'slug' => array_keys($content['services'])[$index],
                'parent_id' => $services->id,
                'template' => PageTemplate::Service,
                'sort_order' => $index,
            ]);
        }

        $this->createPage($content['pages']['gallery'], [
            'title' => 'Gallery', 'slug' => 'gallery', 'template' => PageTemplate::Gallery,
            'sort_order' => 2, 'show_in_nav' => true,
        ]);

        $this->createPage($content['pages']['faq'], [
            'title' => 'FAQ', 'slug' => 'faq', 'template' => PageTemplate::Faq,
            'sort_order' => 3, 'show_in_nav' => true,
        ]);

        $this->createPage($content['pages']['contact'], [
            'title' => 'Contact', 'slug' => 'contact', 'template' => PageTemplate::Contact,
            'sort_order' => 4, 'show_in_nav' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $attributes
     */
    private function createPage(array $content, array $attributes): Page
    {
        foreach (['hero_image', 'circle_image'] as $imageField) {
            if (isset($content[$imageField])) {
                $content[$imageField] = $this->importImage($content[$imageField], 'pages');
            }
        }

        return Page::create([...$content, ...$attributes]);
    }

    private function importImage(string $filename, string $directory): string
    {
        $path = "{$directory}/{$filename}";

        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, file_get_contents(public_path("assets/img/{$filename}")));
        }

        return $path;
    }
}
