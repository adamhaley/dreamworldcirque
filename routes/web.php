<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', fn () => response(
    "User-agent: *\nDisallow:".(config('site.noindex') ? ' /' : '')."\n",
    headers: ['Content-Type' => 'text/plain'],
));

Route::get('/', PageController::class)->name('page.home');
Route::fallback(PageController::class)->name('page.show');
