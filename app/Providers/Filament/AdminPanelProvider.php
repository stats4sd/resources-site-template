<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\Widgets;
use Filament\FontProviders\LocalFontProvider;
use Filament\PanelProvider;
use App\Filament\Pages\Login;
use App\Filament\Pages\SiteContentPage;
use App\Filament\Pages\SiteOptionsPage;
use Filament\Support\Colors\Color;
use App\Filament\Resources\TagResource;
use Filament\Navigation\NavigationGroup;
use App\Filament\Resources\TroveResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationBuilder;
use App\Filament\Resources\TagTypeResource;
use ChrisReedIO\Socialment\SocialmentPlugin;
use App\Filament\Resources\TroveTypeResource;
use Filament\SpatieLaravelTranslatablePlugin;
use App\Filament\Resources\CollectionResource;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->homeUrl('/home')
            ->path('/admin')
            ->login(Login::class)
            ->profile()
            ->colors([
                'primary' => $this->brandPrimary(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    ->items([

                        ...TroveResource::getNavigationItems(),
                        ...CollectionResource::getNavigationItems(),
                    ])
                    ->groups([
                        NavigationGroup::make('Details')
                            ->items([
                                ...TroveTypeResource::getNavigationItems(),
                                ...TagTypeResource::getNavigationItems(),
                                ...TagResource::getNavigationItems(),
                            ]),
                        NavigationGroup::make('Site Settings')
                            ->items([
                                ...SiteOptionsPage::getNavigationItems(),
                                ...SiteContentPage::getNavigationItems(),
                            ]),
                    ]);
            })
            ->plugins([
                SocialmentPlugin::make()
                    ->registerProvider('azure', 'fab-microsoft', config('branding.org_name') . ' Staff (via Azure)'),
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(array_keys(config('branding.locales', ['en' => 'English']))),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Inter', provider: LocalFontProvider::class);
    }

    private function brandPrimary(): array
    {
        $css = file_get_contents(resource_path('css/app.css'));
        preg_match('/--brand-primary:\s*(#[0-9a-fA-F]{3,8})/', $css, $matches);

        return isset($matches[1]) ? Color::hex($matches[1]) : Color::Amber;
    }
}
