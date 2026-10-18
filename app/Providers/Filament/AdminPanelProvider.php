<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\AuthenticateAdminPanel;
use App\Http\Middleware\RedirectRetiredAdminUrls;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->profile(EditProfile::class)
            ->passwordReset()
            ->brandName('Shooting Sports')
            ->brandLogo(asset('images/brand/logo-lockup.png'))
            ->brandLogoHeight('2.5rem')
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => view('filament.favicons')->render(),
            )
            // Hide the "0" badge on the table filters trigger — a bare
            // zero on every table is noise. The vendor view sets it
            // unconditionally, so we strip it client-side after render
            // and keep it in sync via a mutation observer.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.admin-tweaks')->render(),
            )
            ->viteTheme('resources/css/filament/admin/theme.css')
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
            ->navigationGroups([
                // Phase 2: Home, Inbox, Advertising and Users are
                // top-level (null group). The two groups below hold
                // everything that lives behind one entry point.
                'Directory',
                'System',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->userMenuItems([
                // Staff can switch to the match-director desk from the
                // admin user menu. Non-staff never see this item — the
                // visible() guard runs per-render.
                'desk' => MenuItem::make()
                    ->label('Open desk')
                    ->url('/desk')
                    ->icon(Heroicon::OutlinedRectangleGroup)
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
                RedirectRetiredAdminUrls::class,
            ])
            ->authMiddleware([
                // Soft-redirect wrong-panel users (desk/shooter) to
                // their rightful home instead of a bare 403. Hard
                // staff-only gate still enforced by canAccessPanel().
                AuthenticateAdminPanel::class,
            ]);
    }
}
