<?php

namespace App\Http\Controllers;

use App\Enums\PageTemplate;
use App\Models\FaqItem;
use App\Models\GalleryPhoto;
use App\Models\Page;
use App\Models\Quote;
use App\Models\Spotlight;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $page = Page::query()
            ->where('path', trim($request->path(), '/'))
            ->when(! $request->user(), fn ($query) => $query->where('is_published', true))
            ->with(['parent', 'quote'])
            ->firstOrFail();

        $response = response()->view($page->template->view(), [
            'page' => $page,
            ...$this->templateData($page),
        ]);

        if (config('site.noindex')) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    /** @return array<string, mixed> */
    private function templateData(Page $page): array
    {
        return match ($page->template) {
            PageTemplate::Home => [
                'spotlights' => Spotlight::query()->orderBy('sort_order')->get(),
                'services' => Page::query()
                    ->where('template', PageTemplate::ServicesIndex)
                    ->with(['children' => fn ($query) => $query->where('is_published', true)])
                    ->first(),
                'carouselPhotos' => GalleryPhoto::query()->where('in_homepage_carousel', true)->orderBy('sort_order')->get(),
                'quotes' => Quote::query()->active()->get(),
                'prefetchHeroImages' => Page::query()
                    ->where('is_published', true)
                    ->where('template', '!=', PageTemplate::Home)
                    ->whereNotNull('hero_image')
                    ->pluck('hero_image')
                    ->unique()
                    ->values(),
            ],
            PageTemplate::ServicesIndex => [
                'page' => $page->load(['children' => fn ($query) => $query->where('is_published', true)]),
            ],
            PageTemplate::Gallery => [
                'photos' => GalleryPhoto::query()->orderBy('sort_order')->get(),
            ],
            PageTemplate::Faq => [
                'faqItems' => FaqItem::query()->orderBy('sort_order')->get(),
            ],
            default => [],
        };
    }
}
