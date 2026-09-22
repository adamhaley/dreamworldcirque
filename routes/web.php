<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Chat\AskChatQuestionController;
use App\Http\Controllers\Chat\StoreChatLeadController;
use App\Http\Controllers\PageController;
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

Route::get('/', PageController::class)->name('page.home');
Route::fallback(PageController::class)->name('page.show');
