<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Trove;
use Filament\Forms\Form;
use App\Models\Collection;
use Filament\Tables\Table;
use App\Models\Organisation;
use Filament\Actions\ViewAction;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Resources\Concerns\Translatable;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CollectionResource\Pages;
use App\Filament\Translatable\Form\TranslatableComboField;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;
use App\Filament\Resources\CollectionResource\RelationManagers;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use App\Filament\Resources\CollectionResource\Pages\ViewCollection;

class CollectionResource extends Resource
{
    use Translatable;

    protected static ?string $model = Collection::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TranslatableComboField::make('title')
                    ->icon('heroicon-o-exclamation-circle')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Collection Title')
                    ->description('Add a useful title for the collection.')
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class)
                    ->required(),

                TranslatableComboField::make('description')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Describe the Collection')
                    ->description('For example: What is this collection? Who is it for? Why was it curated?')
                    ->childField(Forms\Components\MarkdownEditor::class)
                    ->required(),

                Section::make('cover_image')
                    ->icon('heroicon-o-photo')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->heading(__('Cover Image'))
                    ->description(__('Add a cover image for the resource. This will be displayed on the front-end.'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\SpatieMediaLibraryFileUpload::make('cover_image_en')
                            ->label('English')
                            ->collection('cover_image_en')
                            ->disk('s3'),
                        Forms\Components\SpatieMediaLibraryFileUpload::make('cover_image_es')
                            ->label('Spanish')
                            ->collection('cover_image_es')
                            ->disk('s3'),
                        Forms\Components\SpatieMediaLibraryFileUpload::make('cover_image_fr')
                            ->label('French')
                            ->collection('cover_image_fr')
                            ->disk('s3'),
                    ]),
                Forms\Components\Hidden::make('uploader_id')
                    ->default(Auth::id()),

                Forms\Components\Section::make('Metadata')
                    ->icon('heroicon-o-document-chart-bar')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->schema([

                        Forms\Components\Select::make('organisation_id')
                            ->label('Who is the owner of this collection?')
                            ->placeholder('Select the organisation that owns this collection')
                            ->default(function () {
                                $defaultOrg = Organisation::firstWhere('name', 'Stats4SD');
                                return $defaultOrg?->id ?? null;
                            })
                            ->options(function () {
                                return \App\Models\Organisation::pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(),

                        Forms\Components\Hidden::make('uploader_id')->default(Auth::user()->id),
                    ]),

                Section::make('Publishing')
                    ->icon('heroicon-o-globe-alt')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->schema([

                        Forms\Components\Checkbox::make('public')
                            ->label('Should this collection be public?')
                            ->hint('If yes, the collection will appear on the front-end. We suggest you keep the collection private until it is ready to be shared.')
                            ->default(0),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('organisation.name')
                    ->label('Owner')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->wrap(),
                Tables\Columns\SpatieMediaLibraryImageColumn::make('cover_image')
                    ->collection(fn(Pages\ListCollections $livewire) => 'cover_image_' . $livewire->activeLocale)
                    ->action(
                        Tables\Actions\Action::make('view_image')
                            ->modalHeading(fn(Collection $record, Pages\ListCollections $livewire) => $record->title . ' - Cover Image (' . $livewire->activeLocale . ')')
                            ->modalContent(fn(Collection $record, Pages\ListCollections $livewire) => new HtmlString('<img src="' . $record->getFirstMediaUrl('cover_image_' . $livewire->activeLocale) . '" class="w-full h-auto">'))
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                    ),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Upload Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Curated By')
                    ->sortable(),
                Tables\Columns\TextColumn::make('troves_count')
                    ->counts('troves')
                    ->label('# Troves')
                    ->sortable(),
                Tables\Columns\IconColumn::make('public')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-mark')
                    ->sortable()
            ])
            ->filters([
                SelectFilter::make('owner')
                    ->relationship('organisation', 'name')
            ])
            ->actions([
                CommentsAction::make(),
                Tables\Actions\ViewAction::make(),
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
            RelationManagers\TrovesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCollections::route('/'),
            'create' => Pages\CreateCollection::route('/create'),
            'edit' => Pages\EditCollection::route('/{record}/edit'),
            'view' => Pages\ViewCollection::route('/{record}'),
        ];
    }
}
