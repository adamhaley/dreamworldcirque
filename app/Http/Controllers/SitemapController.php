<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = Page::query()->where('is_published', true)->orderBy('path')->get();

        // url() strips trailing slashes, but every page URL and nav link on
        // this site consistently keeps one (see Page::url()) -- concatenating
        // onto the app URL directly instead preserves that canonical form.
        $urls = $pages->map(fn (Page $page): string => sprintf(
            "  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n  </url>\n",
            e(rtrim(config('app.url'), '/').$page->url()),
            $page->updated_at->toAtomString(),
        ))->implode('');

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".
            "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n".
            $urls.
            '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
