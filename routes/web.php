<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Chat\AskChatQuestionController;
use App\Http\Controllers\Chat\StoreChatLeadController;
use App\Http\Controllers\FaviconController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ShowProspectReplyAttachmentController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StoreContactSubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::post('/chat/ask', AskChatQuestionController::class)
    ->middleware('throttle:20,1')
    ->name('chat.ask');

Route::post('/chat/lead', StoreChatLeadController::class)
    ->middleware('throttle:5,1')
    ->name('chat.lead');

Route::post('/contact', StoreContactSubmissionController::class)
    ->middleware('throttle:5,1')
    ->name('contact.store');

Route::get('/robots.txt', fn () => response(
    "User-agent: *\nDisallow:".(config('site.noindex') ? ' /' : '')."\n",
    headers: ['Content-Type' => 'text/plain'],
));

Route::get('/favicon-{size}.png', FaviconController::class)
    ->whereIn('size', ['48', '180', '192'])
    ->name('favicon');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// The legacy Adobe Muse site used flat .html filenames -- these keep any
// existing search rankings, bookmarks, and external backlinks working
// instead of 404ing once dreamworldcirque.com pointed here.
Route::permanentRedirect('/about-us.html', '/about/');
Route::permanentRedirect('/get-a-quote.html', '/contact/');
Route::permanentRedirect('/showcase.html', '/gallery/');
Route::permanentRedirect('/video.html', '/');
Route::permanentRedirect('/index.html', '/');
// Muse's own default export filename for the homepage -- caught via
// Search Console flagging it as a 404 still being crawled from the old
// site, not something currently linked anywhere on the new one.
Route::permanentRedirect('/default.htm', '/');

Route::get('/admin/prospects/{prospect}/reply-attachments/{index}', ShowProspectReplyAttachmentController::class)
    ->whereNumber('index')
    ->name('admin.prospect-reply-attachments.show');

Route::get('/', PageController::class)->name('page.home');
Route::fallback(PageController::class)->name('page.show');
