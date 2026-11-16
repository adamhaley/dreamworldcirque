<?php

namespace App\Providers;

use App\Ai\PreviewAgentFakes;
use App\Models\FaqItem;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        PreviewAgentFakes::register();

        RateLimiter::for('openai', fn (object $job): Limit => Limit::perMinute(30)->by('openai'));

        View::composer('components.chat-widget', function (ViewInstance $view): void {
            $view->with('chatFaqs', FaqItem::query()
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'question', 'answer']));
        });

        View::composer(['components.layouts.site', 'pages.*'], function (ViewInstance $view): void {
            $view->with('settings', once(fn (): SiteSetting => SiteSetting::current()));
        });

        View::composer('components.layouts.site', function (ViewInstance $view): void {
            $view->with('navPages', Page::query()
                ->whereNull('parent_id')
                ->where('show_in_nav', true)
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->with(['children' => fn ($query) => $query->where('is_published', true)])
                ->get());
        });
    }
}
