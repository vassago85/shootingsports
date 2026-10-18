<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Models\User;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
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
use Illuminate\View\Middleware\ShareErrorsFromSession;

class DeskPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('desk')
            ->path('desk')
            ->login(Login::class)
            // Registration is no longer served from the desk panel — the
            // public /register flow owns all signups (shooter, MD,
            // supplier — one form, role tickboxes). /desk/register
            // 301s to /register in routes/web.php.
            ->passwordReset()
            ->profile(EditProfile::class)
            ->brandName('Shooting Sports Desk')
            ->brandLogo(asset('images/brand/logo-lockup.png'))
            ->brandLogoHeight('2.5rem')
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => view('filament.favicons')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.admin-tweaks')->render(),
            )
            ->viteTheme('resources/css/filament/desk/theme.css')
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                'primary' => Color::hex('#6B7D3A'),
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
            // Mockup s3/s5 nav groups. Hunting / Big Game stay parked.
            ->navigationGroups([
                'Your clubs',
                'Your business',
                'More',
                'Club',
                'Billing',
                'Stories',
            ])
            ->discoverResources(in: app_path('Filament/Desk/Resources'), for: 'App\\Filament\\Desk\\Resources')
            ->discoverPages(in: app_path('Filament/Desk/Pages'), for: 'App\\Filament\\Desk\\Pages')
            ->discoverWidgets(in: app_path('Filament/Desk/Widgets'), for: 'App\\Filament\\Desk\\Widgets')
            ->navigationItems([
                // Mockup s5 — industry-only side nav. Directors never see these.
                NavigationItem::make('My business')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->group('Your business')
                    ->sort(10)
                    ->url(fn (): string => self::industryEditUrl())
                    ->visible(fn (): bool => self::isIndustryNavVisible()),
                NavigationItem::make('Advertise with us')
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->group('More')
                    ->sort(30)
                    ->url(fn (): string => route('advertise'))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => self::isIndustryNavVisible()),
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->userMenuItems([
                // Mirror of the admin panel's hop — staff can jump
                // straight back into /admin from the desk user menu.
                // Non-staff never see this item.
                'admin' => MenuItem::make()
                    ->label('Open admin')
                    ->url('/admin')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->visible(fn (): bool => (bool) auth()->user()?->is_staff),
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
                ApplySecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    protected static function isIndustryNavVisible(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->isIndustryDeskUser()
            && ! $user->isDirectorDeskUser();
    }

    protected static function industryEditUrl(): string
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return route('suppliers.onboard');
        }

        $provider = $user->providers()->orderByDesc('updated_at')->first();

        return $provider !== null
            ? route('suppliers.onboard.edit', ['provider' => $provider->slug])
            : route('suppliers.onboard');
    }
}
