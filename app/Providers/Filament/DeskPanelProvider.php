<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class DeskPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('desk')
            ->path('desk')
            ->login()
            // Registration is no longer served from the desk panel — the
            // public /directors/register flow owns MD signup so we can
            // set is_match_director on create. /desk/register 301s to
            // the new route in routes/web.php.
            ->passwordReset()
            ->profile(EditProfile::class)
            ->brandName('Shooting Sports Desk')
            ->favicon(asset('favicon.svg'))
            ->viteTheme('resources/css/filament/desk/theme.css')
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                'primary' => Color::hex('#D9AE52'),
                'gray' => Color::Zinc,
                'danger' => Color::Rose,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'teal' => Color::Teal,
                'cyan' => Color::Cyan,
            ])
            ->font('IBM Plex Sans')
            ->darkMode(true, isForced: true)
            ->themeSwitcher(false)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'Your listings',
                'Calendar',
            ])
            ->discoverResources(in: app_path('Filament/Desk/Resources'), for: 'App\\Filament\\Desk\\Resources')
            ->discoverPages(in: app_path('Filament/Desk/Pages'), for: 'App\\Filament\\Desk\\Pages')
            ->discoverWidgets(in: app_path('Filament/Desk/Widgets'), for: 'App\\Filament\\Desk\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
