<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\Widgets;
use Filament\PanelProvider;
use App\Filament\Pages\Login;
use Filament\Support\Colors\Color;
use App\Filament\Resources\HubResource;
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
use App\Filament\Resources\OrganisationResource;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Kainiklas\FilamentScout\FilamentScoutPlugin;
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
            ->colors([
                'primary' => Color::Amber,
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
                        // ...HubResource::getNavigationItems(),
                    ])
                    ->groups([
                        NavigationGroup::make('Details')
                            ->items([
                                ...TroveTypeResource::getNavigationItems(),
                                ...TagTypeResource::getNavigationItems(),
                                ...TagResource::getNavigationItems(),
                            ]),
                        NavigationGroup::make('Admin')
                            ->items([
                                ...OrganisationResource::getNavigationItems(),
                            ]),
                    ]);
            })
            ->plugins([
                SocialmentPlugin::make()
                    ->registerProvider('azure', 'fab-microsoft', 'Stats4SD Staff (via Azure)'),
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'es', 'fr']),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
