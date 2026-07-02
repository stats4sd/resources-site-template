<?php

namespace App\Filament\Resources;

use App\Models\Tag;
use Filament\Forms;
use Filament\Tables;
use App\Models\Trove;
use App\Models\TagType;
use Livewire\Component;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Awcodes\Shout\Components\Shout;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Concerns\Translatable;
use App\Filament\Resources\TroveResource\Pages;
use App\Models\Scopes\PublishedScope;
use Kainiklas\FilamentScout\Traits\InteractsWithScout;
use App\Filament\Translatable\Form\TranslatableComboField;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;
use App\Filament\Forms\Components\Actions\SaveDraftFormAction;

class TroveResource extends Resource
{
    use Translatable;
    use InteractsWithScout;

    protected static ?string $model = Trove::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static int $globalSearchResultsLimit = 100;

    /**
     * The admin manages every version, so opt out of the public PublishedScope.
     * List tabs and edit-record resolution then narrow this to working versions.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(PublishedScope::class);
    }

    public static function getRecordTitleAttribute(): ?string
    {
        $locale = app()->getLocale();

        return "title";
    }

    public static function form(Form $form): Form
    {

        $tagFields = self::getTagFields();

        return $form
            ->schema([
                Wizard::make([

                    Wizard\Step::make('Details')
                        ->icon('heroicon-m-information-circle')
                        ->columns(1)
                        ->schema([
                            TranslatableComboField::make('title')
                                ->icon('heroicon-o-exclamation-circle')
                                ->iconColor('primary')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->columns(3)
                                ->heading(__('Title'))
                                ->hint(__('Add a useful title for the resource, this could be the title of the document, or the name of the software, etc.'))
                                ->childField(
                                    TextInput::class,
                                )
                                ->required(),
                            TranslatableComboField::make('description')
                                ->icon('heroicon-o-document-text')
                                ->iconColor('primary')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->columns(1)
                                ->heading(__('Description'))
                                ->hint(__('For example: What is this trove? Who is it for? Why was it made or uploaded?'))
                                ->childField(
                                    Forms\Components\RichEditor::make('description')
                                    ->disableToolbarButtons([
                                        'attachFiles'
                                    ]),
                                )
                                ->required(),

                            Forms\Components\Section::make('Metadata')
                                ->icon('heroicon-o-document-chart-bar')
                                ->iconColor('primary')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->description(__('Key metadata for filters and search'))
                                ->schema([

                                    Forms\Components\Select::make('troveTypes')
                                        ->label('What type(s) of resource is this?')
                                        ->relationship('troveTypes', 'label')
                                        ->multiple()
                                        ->preload()
                                        ->placeholder('Select the resource type')
                                        ->relationship('troveTypes', 'label')
                                        ->required()
                                        ->getOptionLabelFromRecordUsing(fn($record, $livewire) => $record->getTranslation('label', 'en')),

                                    Forms\Components\Select::make('source')
                                        ->placeholder('Select the origin of the resource')
                                        ->options([0 => 'Internal', 1 => 'External'])
                                        ->required(),

                                    Forms\Components\DatePicker::make('creation_date')
                                        ->label('When was the resource created?')
                                        ->helperText('To the nearest month (approximately is fine). This is mainly to highlight to users when a resource might be a bit out of date.')
                                        ->minDate(now()->subYears(30))
                                        ->maxDate(now())
                                        ->required()
                                        ->default(now()),

                                    Forms\Components\Hidden::make('uploader_id')->default(Auth::user()->id),
                                ]),
                        ]),

                    Wizard\Step::make('Tags')
                        ->icon('heroicon-m-tag')
                        ->schema([
                            Forms\Components\Section::make('Tags')
                                ->icon('heroicon-m-tag')
                                ->iconColor('primary')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->description('These tags help organise and filter the resources on the front-end. Except where specified, you must select from existing tags. If you believe a new tag is required, please contact Emily. You can apply as many tags as you need for each category.')
                                ->columns(2)
                                ->schema($tagFields),
                        ]),

                    Wizard\Step::make('Content')
                        ->icon('heroicon-m-link')
                        ->schema([
                            // for file uploads, have 3 separate fields to put them into different collections
                            Section::make('files')
                                ->icon('heroicon-o-document')
                                ->iconColor('primary')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->heading(__('Files'))
                                ->description(__('A trove will often contain multiple files. These are files that are part of the same set, like a powerpoint presentation and the presenter\'s own notes, or question and answer sheets of a quiz'))
                                ->columns(min(3, count(config('branding.locales', ['en' => 'English']))))
                                ->schema(
                                    collect(config('branding.locales', ['en' => 'English']))->map(fn ($label, $locale) =>
                                        Forms\Components\Group::make([
                                            Forms\Components\SpatieMediaLibraryFileUpload::make("files_{$locale}")
                                                ->label($label)
                                                ->multiple()
                                                ->reorderable()
                                                ->downloadable()
                                                ->preserveFilenames()
                                                ->collection("content_{$locale}")
                                                ->disk(config('media-library.disk_name')),
                                            Forms\Components\TextInput::make("file_name_{$locale}")
                                                ->label("{$label} file display name")
                                                ->placeholder('Optional - defaults to filename')
                                                ->dehydrated(false)
                                                ->afterStateHydrated(function ($component, $record) use ($locale) {
                                                    if ($record) {
                                                        $component->state($record->getMedia("content_{$locale}")->first()?->name);
                                                    }
                                                }),
                                        ])
                                    )->values()->all()
                                ),

                            TranslatableComboField::make('external_links')
                                ->icon('heroicon-o-link')
                                ->iconColor('primary')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->heading(__('External Links'))
                                ->hint(__('Websites, files etc., hosted by other people'))
                                ->childField(
                                    Repeater::make('-')
                                        ->label('-')
                                        ->schema([
                                            TextInput::make('link_title'),
                                            TextInput::make('link_url')
                                                ->label('Link URL'),
                                        ])
                                        ->columns(1)
                                        ->addActionLabel('Add a link')
                                ),

                            TranslatableComboField::make('youtube_links')
                                ->icon('heroicon-o-video-camera')
                                ->iconColor('primary')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->heading(__('YouTube Videos'))
                                ->hint('Add the youtube id if you have added a video file that already exists on YouTube. On YouTube, when you hit "share", the id is the random-like string after https://youtu.be/')
                                ->columns(3)
                                ->childField(
                                    Forms\Components\Repeater::make('youtube_links')
                                        ->schema([
                                            Forms\Components\TextInput::make('youtube_id')
                                                ->label('YouTube ID'),
                                        ])
                                        ->addActionLabel('Add a YouTube video'),
                                ),
                        ]),

                    Wizard\Step::make('Cover Image')
                        ->icon('heroicon-m-photo')
                        ->schema([
                            Section::make('cover_image')
                                ->icon('heroicon-o-photo')
                                ->iconColor('primary')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->heading(__('Cover Image'))
                                ->description(__('Add a cover image for the resource. This will be displayed on the front-end.'))
                                ->columns(min(3, count(config('branding.locales', ['en' => 'English']))))
                                ->schema(
                                    collect(config('branding.locales', ['en' => 'English']))->map(fn ($label, $locale) =>
                                        Forms\Components\SpatieMediaLibraryFileUpload::make("cover_image_{$locale}")
                                            ->label($label)
                                            ->collection("cover_image_{$locale}")
                                    )->values()->all()
                                ),
                        ]),
                    Wizard\Step::make('Check')
                        ->icon('heroicon-m-clipboard-document-check')
                        ->schema([
                            Shout::make('check')
                                ->content(new HtmlString(
                                    '
<h4 class="text-lg mb-2">Review and Publish</h4>
<p>Once the trove is ready to be published, we recommend that you invite someone to check it over, to catch any issues. Requesting a review records who you asked, and the trove appears in the <b>Check Requested</b> tab of the trove list for the team to pick up.</p>
<p>If you are happy with the trove, you can publish it immediately by clicking the <b>Publish</b> button below to make it live on the site.</p>'
                                ))
                                ->type('info'),

                            Forms\Components\Section::make('')
                                ->extraAttributes(['class' => 'grey-box'])
                                ->schema([
                                    Forms\Components\Radio::make('next_steps')
                                        ->dehydrated(false)
                                        ->label('What do you want to do with this trove?')
                                        ->inlineLabel()
                                        ->inline()
                                        ->options([
                                            'save' => 'Save the trove as a draft',
                                            'review' => 'Request a Review / Check',
                                            'publish' => 'Publish it!',
                                        ])
                                        ->live(),
                                ]),

                            Forms\Components\Grid::make([
                                'default' => 3,
                                'sm' => 1,
                                'lg' => 3,
                            ])
                                ->schema([
                                    Forms\Components\Fieldset::make('Save as Draft')
                                        ->columns(1)
                                        ->visible(fn(Forms\Get $get) => $get('next_steps') === 'save')
                                        ->schema([
                                            Shout::make('save_draft')
                                                ->content(new HtmlString('Save the trove with the current changes, but do not publish it. You can come back to it later to finish it. Use the "Save Draft" button below')),
                                            Forms\Components\Actions::make([SaveDraftFormAction::make()])
                                                ->alignEnd(),
                                        ]),
                                    Forms\Components\Fieldset::make('Check Request')
                                        ->columns(1)
                                        ->visible(fn(Forms\Get $get) => $get('next_steps') === 'review')
                                        ->schema([

                                            // requester_id is stamped in the page's save handler
                                            // (auth id when a checker is chosen) — not derived here.
                                            Forms\Components\Select::make('checker_id')
                                                ->label('Select the person to ask')
                                                ->relationship('checker', 'name')
                                                ->live(),


                                            Forms\Components\Actions::make([
                                                SaveDraftFormAction::make()
                                                    ->label('Save as Draft and Request Review'),
                                            ])
                                                ->alignEnd(),
                                        ]),
                                    Forms\Components\Fieldset::make('Publish it')
                                        ->columns(1)
                                        ->visible(fn(Forms\Get $get) => $get('next_steps') === 'publish')
                                        ->schema([
                                            Shout::make('publish_it')
                                                ->content(new HtmlString('Publish the trove with the current changes. This will make it live on the site. Use the "Save and Publish" button below')),

                                            Shout::make('are_you_sure')
                                                ->type('warning')
                                                ->visible(fn(Forms\Get $get) => !$get('checker_id'))
                                                ->content(new HtmlString('It looks like no-one has been asked to check this trove. Are you sure you want to publish it?')),

                                            Shout::make('are_you_sure_again')
                                                ->type('warning')
                                                ->visible(fn(?Trove $record) => $record?->requester_id === auth()->id())
                                                ->content(new HtmlString('It looks like you previously asked someone else to check this trove. Are you sure you want to publish it before it is checked?')),

                                            Forms\Components\Checkbox::make('should_publish')
                                                ->dehydrated(false)
                                                ->label('I am sure I want to publish this trove')
                                                ->visible(fn(?Trove $record, Forms\Get $get) => !$get('checker_id') || $record?->requester_id === auth()->id())
                                                ->live()
                                                ->required(),


                                            Forms\Components\Actions::make([
                                                Forms\Components\Actions\Action::make('Save and Publish')
                                                    ->label(fn(?Trove $record) => $record?->has_published_version ? __('Save and Publish Changes') : __('Save and Publish'))
                                                    ->disabled(fn(Forms\Get $get) => !$get('checker_id') && !$get('should_publish'))
                                                    ->action(function ($livewire) {
                                                        $livewire->shouldPublish = true;

                                                        if ($livewire instanceof CreateRecord) {
                                                            $livewire->create();
                                                        }

                                                        if ($livewire instanceof EditRecord) {
                                                            $livewire->save();
                                                        }
                                                    }),
                                            ])
                                                ->alignEnd(),
                                        ]),
                                ]),
                        ]),
                ])
                ->skippable(fn(Component $livewire) => $livewire instanceof EditRecord),
            ])
            ->columns(1);
    }

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->columns(static::getTableColumns())
            ->filters(static::getTableFilters())
            ->filtersTriggerAction(fn($action) => $action->button()->label('Filters'))
            ->filtersLayout(fn() => FiltersLayout::AboveContentCollapsible)
            ->actions([
                CommentsAction::make(),
                Tables\Actions\Action::make('preview_trove')
                    ->label('Preview on Front-end')
                    ->icon('heroicon-o-eye')
                    ->url(function (Trove $record) {
                        return $record->is_published
                            ? url('/resources/' . $record->slug)
                            : url('/resources/preview/' . $record->slug);
                    })
                    ->openUrlInNewTab()
                    ->action(null)
                    ->link(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTroves::route('/'),
            'create' => Pages\CreateTrove::route('/create'),
            'edit' => Pages\EditTrove::route('/{record}/edit'),
        ];
    }

    private static function getTagFields(): array
    {
        return TagType::all()->map(function (TagType $tagType) {

            $field = Forms\Components\Select::make("tags_{$tagType->slug}")
                ->relationship(
                    name: 'tags',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn(Builder $query) => $query->where('type_id', $tagType->id))
                ->label($tagType->label)
                ->placeholder('Select tags')
                ->multiple()
                ->preload()
                ->loadingMessage('Loading tags...')
                ->noSearchResultsMessage('No tags match your search')
                ->getOptionLabelFromRecordUsing(fn($record, $livewire) => $record->getTranslation('name', 'en'))
                ->hintIcon(
                    icon: 'heroicon-m-question-mark-circle',
                    tooltip: fn() => $tagType->description
                );

            if ($tagType->freetext) {
                return $field
                    ->createOptionForm([
                        TranslatableComboField::make('name')
                            ->required()
                            ->unique('tags', 'name', ignoreRecord: true)
                            ->icon('heroicon-s-tag')
                            ->iconColor('primary')
                            ->extraAttributes(['class' => 'grey-box'])
                            ->label('Name')
                            ->description('Enter the name of the tag')
                            ->columns(3)
                            ->childField(Forms\Components\TextInput::class),
                    ])
                    ->createOptionUsing(function (array $data) use ($tagType) {

                        $tag = Tag::Create([
                            'name' => $data['name'],
                            'type_id' => $tagType->id,
                        ]);

                        return $tag->id;
                    });
            }

            return $field;

        })
            ->toArray();
    }

    public static function getTableColumns(): array
    {
        return [
            TextColumn::make('title')
                ->wrap()
                ->sortable(query: fn(Builder $query, $direction) => $query->orderBy('title->' . app()->currentLocale(), $direction)),
            SpatieMediaLibraryImageColumn::make('cover_image')
                ->collection(fn(Component $livewire) => 'cover_image_' . $livewire->activeLocale),
            TextColumn::make('created_at')
                ->label('Upload date')
                ->date()
                ->sortable(),
            TextColumn::make('updated_at')
                ->label('Last Updated')
                ->date()
                ->sortable(),
            TextColumn::make('creation_date')
                ->date()
                ->sortable(),
            TextColumn::make('user.name')
                ->label('Uploader')
                ->sortable(),
            TextColumn::make('download_count')
                ->label('# Downloads')
                ->sortable(),
        ];
    }

    /**
     * @throws \Exception
     */
    public static function getTableFilters(): array
    {
        $tagFilters = TagType::all()->map(function (TagType $tagType) {
            return SelectFilter::make("tags_{$tagType->slug}")
                ->label($tagType->label)
                ->relationship('tags', 'name', function (Builder $query) use ($tagType) {
                    $query->where('type_id', $tagType->id);
                })
                ->multiple()
                ->preload();
        })->toArray();

        return [
            SelectFilter::make('source')
                ->options([0 => 'Internal', 1 => 'External']),
            SelectFilter::make('resourceType')
                ->relationship('troveTypes', 'label')
                ->getOptionLabelFromRecordUsing(fn($record, $livewire) => $record->getTranslation('label', 'en')),
            SelectFilter::make('uploader')
                ->relationship('user', 'name'),
            ...$tagFilters,
        ];
    }

    public static function getTableFilterSchema(array $filters): array
    {
        return [
            Section::make('Tags')
                ->collapsed()
                ->schema(static::getTagFilterSchema($filters))
                ->columns([
                    'xs' => 1,
                    'sm' => 2,
                    'lg' => 4,
                    '3xl' => 6,
                    '5xl' => 8,
                ]),

            $filters['source'],
            $filters['resourceType'],
            $filters['uploader'],
        ];
    }

    public static function getTagFilterSchema(array $filters): array
    {
        return TagType::all()->map(function (TagType $tagType) use ($filters) {
            return $filters["tags_{$tagType->slug}"];
        })->toArray();
    }
}
