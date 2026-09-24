<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Widgets\ProspectAlignmentBySubmissionChart;
use App\Filament\Widgets\ProspectAverageConfidenceChart;
use App\Filament\Widgets\ProspectClassificationCompositionChart;
use App\Filament\Widgets\ProspectNeedsHumanRateChart;
use App\Models\SiteSetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('Dream World Cirque')
            ->brandLogo(asset('assets/img/logo.png'))
            ->darkModeBrandLogo(asset('assets/img/logo-white.png'))
            ->brandLogoHeight('2.5rem')
            // A closure, not a plain array: `panel()` runs during provider
            // boot for every process (console commands, queue workers, the
            // test suite before migrations run), not just real admin
            // requests. Evaluating SiteSetting::current() eagerly there hits
            // the database before it's ready; Panel::getColors() only calls
            // this closure when a panel is actually being rendered.
            ->colors(fn (): array => [
                'primary' => Color::hex(SiteSetting::current()->accent_color ?: SiteSetting::DefaultAccentColor),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigationGroups([
                NavigationGroup::make('Leads'),
                NavigationGroup::make('Site'),
                NavigationGroup::make('Homepage'),
                NavigationGroup::make('Settings')->icon(Heroicon::OutlinedCog6Tooth)->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('View site')
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->sort(100),
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                ProspectNeedsHumanRateChart::class,
                ProspectAverageConfidenceChart::class,
                ProspectAlignmentBySubmissionChart::class,
                ProspectClassificationCompositionChart::class,
            ])
            // Filament renders its own separately-compiled assets and does
            // not load the app's Vite bundle by default -- without this,
            // window.Echo (from resources/js/echo.js) never exists on
            // /admin pages and the echo-private: Livewire listeners below
            // would silently do nothing.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite(['resources/js/app.js'])"),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
