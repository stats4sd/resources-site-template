<?php

namespace App\Filament\Pages;

use App\Contracts\ResolvesVideoLinks;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\Collection;
use App\Models\SiteContent;
use Filament\Actions\Action as FormAction;
use Filament\Actions\Action as PageAction;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class SiteContentIfaPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Site Content';

    protected static ?string $title = 'Site Content';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $contents = SiteContent::all()->keyBy('key');
        $formData = [];
        foreach (static::contentKeys() as $key) {
            $record = $contents->get($key);
            $formData[$key] = $record ? $record->getTranslations('value') : [];
        }

        $featuredCollectionsRaw = SiteContent::get('home_ifa_featured_collections');
        $formData['featured_collections'] = $featuredCollectionsRaw ? json_decode($featuredCollectionsRaw, true) : [];

        $aboutContentItemsRaw = SiteContent::get('about_content_items');
        $formData['about_content_items'] = $aboutContentItemsRaw ? json_decode($aboutContentItemsRaw, true) : static::defaultAboutContentItems();

        $formData['banner_image'] = SiteContent::get('banner_image');

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Pages')
                    ->tabs([
                        Tab::make('Shared')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                TranslatableComboField::make('nav_library_home_label')
                                    ->label('Home navigation link text')
                                    ->description('Appears in the header navigation linking to the Home page.')
                                    ->icon('heroicon-s-bars-3')
                                    ->iconColor('primary')
                                    ->extraAttributes(['class' => 'grey-box'])
                                    ->collapsed()
                                    ->childField(Forms\Components\TextInput::class),
                                Section::make('Banner image')
                                    ->description('Background image shown behind the hero heading on the Home, About and Students pages.')
                                    ->icon('heroicon-s-photo')
                                    ->iconColor('primary')
                                    ->extraAttributes(['class' => 'grey-box'])
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\FileUpload::make('banner_image')
                                            ->hiddenLabel()
                                            ->image()
                                            ->disk(config('media-library.disk_name'))
                                            ->directory('site-content')
                                            ->visibility('public')
                                            ->imagePreviewHeight('150'),
                                    ]),
                                TranslatableComboField::make('shared_hero_heading')
                                    ->label('Main heading')
                                    ->description('Displayed over the banner image on the Home, About and Students pages.')
                                    ->icon('heroicon-s-globe-alt')
                                    ->iconColor('primary')
                                    ->extraAttributes(['class' => 'grey-box'])
                                    ->collapsed()
                                    ->childField(Forms\Components\TextInput::class),
                                TranslatableComboField::make('footer_admin_login_label')
                                    ->label('Admin login button text')
                                    ->description('Appears as the button label in the site footer, linking to the admin panel.')
                                    ->icon('heroicon-s-arrow-right-end-on-rectangle')
                                    ->iconColor('primary')
                                    ->extraAttributes(['class' => 'grey-box'])
                                    ->collapsed()
                                    ->childField(Forms\Components\TextInput::class),
                            ]),
                        Tab::make('Home')
                            ->icon('heroicon-o-home')
                            ->schema([
                                Section::make()
                                    ->headerActions([
                                        FormAction::make('view_home')
                                            ->label('Open page')
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->url('/home')
                                            ->openUrlInNewTab()
                                            ->color('gray'),
                                    ])
                                    ->schema([
                                        TranslatableComboField::make('home_ifa_heading_line2')
                                            ->label('Subheading')
                                            ->description('Displayed under the main heading, over the banner image.')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\TextInput::class),
                                        TranslatableComboField::make('home_ifa_intro')
                                            ->label('Introduction')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(
                                                Forms\Components\MarkdownEditor::make('_')
                                                    ->toolbarButtons([['bold', 'italic', 'link'], ['undo', 'redo']])
                                            ),
                                        TranslatableComboField::make('home_ifa_collections_heading')
                                            ->label('Collections section heading')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\TextInput::class),
                                        TranslatableComboField::make('home_ifa_collections_intro')
                                            ->label('Collections section introduction')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\Textarea::class),
                                        Section::make('Featured collections')
                                            ->description('Choose which collections appear, in what order, and which icon each one shows.')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->schema([
                                                Forms\Components\Repeater::make('featured_collections')
                                                    ->hiddenLabel()
                                                    ->schema([
                                                        Forms\Components\Select::make('collection_id')
                                                            ->label('Collection')
                                                            ->options(fn () => Collection::query()->get()->mapWithKeys(fn (Collection $collection) => [$collection->id => $collection->title]))
                                                            ->searchable()
                                                            ->required(),
                                                        Forms\Components\Select::make('icon')
                                                            ->label('Icon')
                                                            ->options(collect(static::iconOptions())
                                                                ->mapWithKeys(fn (string $label, string $file) => [
                                                                    $file => '<img src="'.asset('images/ifa/'.$file).'" style="width:1.5rem;height:1.5rem;object-fit:cover;border-radius:9999px;display:inline-block;vertical-align:middle;margin-right:0.5rem;"> '.$label,
                                                                ])
                                                                ->all())
                                                            ->allowHtml()
                                                            ->required(),
                                                    ])
                                                    ->columns(2)
                                                    ->addActionLabel('Add collection')
                                                    ->defaultItems(0),
                                            ]),
                                        TranslatableComboField::make('home_ifa_resources_heading')
                                            ->label('Resources section heading')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\TextInput::class),
                                        TranslatableComboField::make('home_ifa_topics_heading')
                                            ->label('Topics filter heading')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\TextInput::class),
                                        TranslatableComboField::make('home_ifa_institutions_heading')
                                            ->label('Institutions filter heading')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\TextInput::class),
                                        TranslatableComboField::make('home_ifa_syllabi_heading')
                                            ->label('Levels filter heading')
                                            ->icon('heroicon-s-home')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\TextInput::class),
                                    ]),
                            ]),
                        Tab::make('About')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make()
                                    ->headerActions([
                                        FormAction::make('view_about')
                                            ->label('Open page')
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->url('/about')
                                            ->openUrlInNewTab()
                                            ->color('gray'),
                                    ])
                                    ->schema([
                                        TranslatableComboField::make('about_ifa_heading_line2')
                                            ->label('Subheading')
                                            ->description('Displayed under the main heading, over the banner image.')
                                            ->icon('heroicon-s-information-circle')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\TextInput::class),
                                        TranslatableComboField::make('about_ifa_body')
                                            ->label('Introduction')
                                            ->icon('heroicon-s-information-circle')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(
                                                Forms\Components\MarkdownEditor::make('_')
                                                    ->toolbarButtons([['bold', 'italic', 'link'], ['undo', 'redo']])
                                            ),
                                        Section::make('Content items')
                                            ->description('Videos, articles and other resources featured on the About page. YouTube (and other oEmbed-friendly) URLs are embedded automatically; anything else shows a button instead.')
                                            ->icon('heroicon-s-information-circle')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->schema([
                                                Forms\Components\Repeater::make('about_content_items')
                                                    ->hiddenLabel()
                                                    ->schema([
                                                        TranslatableComboField::make('title')
                                                            ->label('Title')
                                                            ->childField(Forms\Components\TextInput::class)
                                                            ->columnSpanFull(),
                                                        TranslatableComboField::make('type')
                                                            ->label('Type')
                                                            ->childField(
                                                                Forms\Components\TextInput::make('_')
                                                                    ->placeholder('Video, Blog, Webinar...')
                                                            )
                                                            ->columnSpanFull(),
                                                        Forms\Components\TextInput::make('source')
                                                            ->label('Source'),
                                                        Forms\Components\TextInput::make('url')
                                                            ->label('URL')
                                                            ->url()
                                                            ->required(),
                                                        TranslatableComboField::make('description')
                                                            ->label('Description')
                                                            ->childField(Forms\Components\Textarea::class)
                                                            ->columnSpanFull(),
                                                        TranslatableComboField::make('button_label')
                                                            ->label('Button text')
                                                            ->description("Only shown when the URL above can't be embedded directly.")
                                                            ->childField(
                                                                Forms\Components\TextInput::make('_')
                                                                    ->default('Read')
                                                            )
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columns(2)
                                                    ->addActionLabel('Add content item')
                                                    ->reorderable()
                                                    ->collapsed()
                                                    ->collapseAllAction(fn (\Filament\Actions\Action $action) => $action->hidden())
                                                    ->expandAllAction(fn (\Filament\Actions\Action $action) => $action->hidden())
                                                    ->itemLabel(fn (array $state): ?string => static::pickLocaleValue($state['title'] ?? null))
                                                    ->defaultItems(0),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Students')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Section::make()
                                    ->headerActions([
                                        FormAction::make('view_students')
                                            ->label('Open page')
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->url('/students')
                                            ->openUrlInNewTab()
                                            ->color('gray'),
                                    ])
                                    ->schema([
                                        TranslatableComboField::make('students_ifa_heading_line2')
                                            ->label('Subheading')
                                            ->description('Displayed under the main heading, over the banner image.')
                                            ->icon('heroicon-s-academic-cap')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(Forms\Components\TextInput::class),
                                        TranslatableComboField::make('students_ifa_intro')
                                            ->label('Introduction')
                                            ->icon('heroicon-s-academic-cap')
                                            ->iconColor('primary')
                                            ->extraAttributes(['class' => 'grey-box'])
                                            ->collapsed()
                                            ->childField(
                                                Forms\Components\MarkdownEditor::make('_')
                                                    ->toolbarButtons([['bold', 'italic', 'link'], ['undo', 'redo']])
                                            ),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    // v5 renders the page through content(); wrap the form schema in a submit form
    // with the Save action in its footer (replaces the old site-content.blade view).
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())->key('form-actions'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $featuredCollections = $data['featured_collections'] ?? [];
        unset($data['featured_collections']);

        $aboutContentItems = static::resolveAboutContentItemUrls($data['about_content_items'] ?? []);
        unset($data['about_content_items']);

        $bannerImage = $data['banner_image'] ?? null;
        unset($data['banner_image']);

        foreach ($data as $key => $value) {
            SiteContent::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $defaultLocale = array_key_first(config('branding.locales', ['en' => 'English']));

        SiteContent::updateOrCreate(
            ['key' => 'home_ifa_featured_collections'],
            ['value' => [$defaultLocale => json_encode($featuredCollections)]]
        );

        SiteContent::updateOrCreate(
            ['key' => 'banner_image'],
            ['value' => [$defaultLocale => $bannerImage ?? '']]
        );

        SiteContent::updateOrCreate(
            ['key' => 'about_content_items'],
            ['value' => [$defaultLocale => json_encode($aboutContentItems)]]
        );

        Notification::make()->success()->title('Saved successfully')->send();
    }

    protected function getFormActions(): array
    {
        return [
            PageAction::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }

    /**
     * Filenames under public/images/ifa/ available as icon badges for featured
     * collection cards, mapped to their admin-facing labels.
     *
     * @return array<string, string>
     */
    private static function iconOptions(): array
    {
        return [
            'teaching_icon.jpg' => 'Teaching',
            'reading_icon.jpg' => 'Reading',
            'video_icon.jpg' => 'Video',
            'webinar_icon.png' => 'Webinar',
        ];
    }

    private static function contentKeys(): array
    {
        return [
            'shared_hero_heading',
            'home_ifa_heading_line2',
            'home_ifa_intro',
            'home_ifa_collections_heading',
            'home_ifa_collections_intro',
            'home_ifa_resources_heading',
            'home_ifa_topics_heading',
            'home_ifa_institutions_heading',
            'home_ifa_syllabi_heading',
            'about_ifa_heading_line2',
            'about_ifa_body',
            'students_ifa_heading_line2',
            'students_ifa_intro',
            'nav_library_home_label',
            'footer_admin_login_label',
        ];
    }

    /**
     * Resolve a translatable repeater field's stored state (a locale-keyed array) to a
     * single display string for the current locale, falling back to the first available
     * translation. Used both by the admin form (item labels) and about.blade.php.
     */
    public static function pickLocaleValue(mixed $value): ?string
    {
        if (! is_array($value)) {
            return $value;
        }

        return $value[app()->getLocale()] ?? (reset($value) ?: null);
    }

    /**
     * Resolve each item's URL into embed data via the same video-link resolver used for
     * Trove video_links (ResolvesVideoLinkFormData), skipping re-resolution when the URL
     * hasn't changed since the last save. The resolver's own 'title'/'url' keys are
     * deliberately not merged in, since those names collide with this item's own fields.
     */
    private static function resolveAboutContentItemUrls(array $items): array
    {
        $resolver = app(ResolvesVideoLinks::class);

        return collect($items)
            ->map(function (array $item) use ($resolver) {
                $url = trim((string) ($item['url'] ?? ''));

                if ($url === '') {
                    return $item;
                }

                if (($item['resolved_url'] ?? null) !== $url) {
                    try {
                        $resolved = $resolver->resolve($url)->toArray();
                    } catch (\Throwable) {
                        $resolved = ['provider' => null, 'embed_url' => null, 'embeddable' => false, 'resolved_url' => null];
                    }

                    $item['provider'] = $resolved['provider'];
                    $item['embed_url'] = $resolved['embed_url'];
                    $item['embeddable'] = $resolved['embeddable'];
                    $item['resolved_url'] = $resolved['resolved_url'];
                }

                if (! str_starts_with((string) ($item['embed_url'] ?? ''), 'https://')) {
                    $item['embed_url'] = null;
                    $item['embeddable'] = false;
                }

                return $item;
            })
            ->all();
    }

    /**
     * Seed data for the content-items repeater, matching what was previously hardcoded
     * in about.blade.php, so nothing disappears before an admin first saves this page.
     * Embed data is pre-filled for the two YouTube items so they render correctly even
     * before that first save triggers real resolution.
     */
    public static function defaultAboutContentItems(): array
    {
        $locale = array_key_first(config('branding.locales', ['en' => 'English']));

        return [
            [
                'title' => [$locale => "Introduction to Let's EAT"],
                'type' => [$locale => 'Video'],
                'source' => 'Colin Anderson',
                'url' => 'https://www.youtube.com/watch?v=8plwv25NYRo',
                'description' => [$locale => "Watch Colin Anderson introduce the Let's EAT community of practice and its approach to transformative agroecology education."],
                'button_label' => [$locale => 'Read'],
                'embeddable' => true,
                'embed_url' => 'https://www.youtube.com/embed/8plwv25NYRo',
                'resolved_url' => 'https://www.youtube.com/watch?v=8plwv25NYRo',
            ],
            [
                'title' => [$locale => 'Transforming Food Systems Through Agroecology Education: Head, Hands, and Heart'],
                'type' => [$locale => 'Blog'],
                'source' => 'AgroecologyNow!',
                'url' => 'https://agroecologynow.net/agroecology-education-head-heart-hands/',
                'description' => [$locale => 'This short blog piece on AgroecologyNow! explores the signature pedagogies — transdisciplinary, experiential, and critical learning — that we consider important for agroecology education to be transformative.'],
                'button_label' => [$locale => 'Read'],
                'embeddable' => false,
                'embed_url' => null,
                'resolved_url' => 'https://agroecologynow.net/agroecology-education-head-heart-hands/',
            ],
            [
                'title' => [$locale => 'Exploring the role of transformative learning for agroecology in higher education'],
                'type' => [$locale => 'Webinar'],
                'source' => 'Agroecology Coalition',
                'url' => 'https://www.youtube.com/watch?v=9J9qexS15w0',
                'description' => [$locale => 'December 2025 - Members of our community of practice discuss their programmes in relation to the signature pedagogies they use. Presented in English and Spanish.'],
                'button_label' => [$locale => 'Watch'],
                'embeddable' => true,
                'embed_url' => 'https://www.youtube.com/embed/9J9qexS15w0',
                'resolved_url' => 'https://www.youtube.com/watch?v=9J9qexS15w0',
            ],
        ];
    }
}
