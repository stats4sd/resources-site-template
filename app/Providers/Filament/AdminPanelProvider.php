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
use App\Filament\Resources\TroveTypeResource;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;
use App\Filament\Resources\CollectionResource;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
                'primary' => $this->brandColour('primary'),
                'success' => $this->brandColour('success'),
                'info' => $this->brandColour('info'),
                'warning' => $this->brandColour('warning'),
                'danger' => $this->brandColour('danger'),
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
                PreventRequestForgery::class,
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
                SpatieTranslatablePlugin::make()
                    ->defaultLocales(array_keys(config('branding.locales', ['en' => 'English']))),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->font('Inter', provider: LocalFontProvider::class);
    }

    /**
     * Filament's own defaults for each of these roles (ColorManager::$defaultColors),
     * used as the fallback when no matching --brand-{$colour} CSS variable is set.
     */
    private const DEFAULT_COLOURS = [
        'danger' => 'red',
        'gray' => 'zinc',
        'info' => 'blue',
        'primary' => 'amber',
        'success' => 'green',
        'warning' => 'amber',
    ];

    private function brandColour(string $colour): array
    {
        $css = file_get_contents(resource_path('css/app.css'));
        preg_match('/--brand-' . preg_quote($colour, '/') . ':\s*(#[0-9a-fA-F]{3,8})/', $css, $matches);

        if (isset($matches[1])) {
            return $this->paletteAnchoredAt600($matches[1]);
        }

        return Color::all()[self::DEFAULT_COLOURS[$colour] ?? 'amber'];
    }

    /**
     * Filament's own Color::hex() (generatePalette) snaps the input to fixed L/C targets
     * per shade, discarding the input color's own lightness/chroma — so --brand-primary
     * rarely renders as the colour actually configured. Instead we anchor the input hex
     * exactly at shade 600 and interpolate the other shades between it and Filament's own
     * near-white (50) / near-black (950) endpoints, preserving the input hue.
     *
     * @return array<int, string>
     */
    private function paletteAnchoredAt600(string $hex): array
    {
        [$targetLightness, $targetChroma, $hue] = sscanf(Color::convertToOklch($hex), 'oklch(%f %f %f)');

        // Filament's default lightness/chroma curve (Color::generatePalette), used here only for its shape.
        $curve = [
            50 => [0.97717647058824, 0.01395454545455],
            100 => [0.95035294117647, 0.03272727272727],
            200 => [0.90547058823529, 0.06318181818182],
            300 => [0.84047058823529, 0.10604545454546],
            400 => [0.75352941176471, 0.15027272727273],
            500 => [0.68270588235294, 0.17009090909091],
            600 => [0.59782352941176, 0.16913636363636],
            700 => [0.51494117647059, 0.14940909090909],
            800 => [0.44611764705882, 0.12331818181818],
            900 => [0.39458823529412, 0.09963636363636],
            950 => [0.27788235294118, 0.07136363636364],
        ];

        $anchorShade = 600;
        $lightEndShade = 50;
        $darkEndShade = 950;

        $palette = [];

        foreach ($curve as $shade => [$lightness, $chroma]) {
            if ($shade === $anchorShade) {
                $palette[$shade] = "oklch({$targetLightness} {$targetChroma} {$hue})";

                continue;
            }

            $endShade = $shade < $anchorShade ? $lightEndShade : $darkEndShade;
            [$endLightness, $endChroma] = $curve[$endShade];

            $lightness = $this->interpolateThroughAnchor($lightness, $curve[$anchorShade][0], $endLightness, $targetLightness, $endLightness);
            $chroma = $this->interpolateThroughAnchor($chroma, $curve[$anchorShade][1], $endChroma, $targetChroma, $endChroma);

            $palette[$shade] = "oklch({$lightness} {$chroma} {$hue})";
        }

        return $palette;
    }

    /**
     * Remaps a curve value that originally ran between [$curveAnchor, $curveEnd] onto
     * [$targetAnchor, $targetEnd], keeping $curveEnd (Filament's near-white/near-black
     * endpoint) fixed and bending the anchor shade to the target colour exactly.
     */
    private function interpolateThroughAnchor(float $curveValue, float $curveAnchor, float $curveEnd, float $targetAnchor, float $targetEnd): float
    {
        if ($curveAnchor === $curveEnd) {
            return $targetAnchor;
        }

        $t = ($curveValue - $curveAnchor) / ($curveEnd - $curveAnchor);

        return $targetAnchor + ($t * ($targetEnd - $targetAnchor));
    }
}
