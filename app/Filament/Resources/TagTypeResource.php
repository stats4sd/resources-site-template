<?php

namespace App\Filament\Resources;

use App\Filament\Translatable\Form\TranslatableComboField;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use App\Models\TagType;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use App\Filament\Resources\TagTypeResource\Pages;
use App\Filament\Resources\TagTypeResource\RelationManagers;

class TagTypeResource extends Resource
{
    use Translatable;

    protected static ?string $model = TagType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('slug')
                    ->label(__('Enter a unique slug'))
                    ->unique(
                        table: 'tag_types',
                        column: 'slug',
                        ignoreRecord: true
                    )
                    ->required()
                    ->rule('alpha_dash'),

                TranslatableComboField::make('label')
                    ->icon('heroicon-s-tag')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Enter the Label for the Tag Type')
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class)
                    ->required(),

                TranslatableComboField::make('description')
                    ->icon('heroicon-s-document-text')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Enter a brief description of the Tag Type')
                    ->childField(Forms\Components\MarkdownEditor::class)
                    ->required(),

                Section::make('')
                    ->schema([
                        Forms\Components\Checkbox::make('freetext')
                            ->label('Can the user add new tags of this type during Trove upload?')
                            ->hintIcon(
                                icon: 'heroicon-m-question-mark-circle',
                                tooltip: 'Most types should not have this enabled, to prevent accidental duplication / mistyping during Trove upload. But this option is available for e.g. "Authors", where new tags are likely to be needed.'),
                        Forms\Components\Toggle::make('show_in_filter')
                            ->label('Show in filter bar')
                            ->hintIcon(
                                icon: 'heroicon-m-question-mark-circle',
                                tooltip: 'When enabled, visitors can filter resources by tags of this type. Within a type, selecting multiple tags uses OR logic. Between types, AND logic applies.')
                            ->live()
                            ->afterStateUpdated(function ($state, $record) {
                                if ($record) {
                                    $record->update(['show_in_filter' => $state]);
                                }
                            }),
                        Forms\Components\Toggle::make('use_custom_tag_order')
                            ->label('Use custom tag order? (default: alphabetical)')
                            ->hintIcon(
                                icon: 'heroicon-m-question-mark-circle',
                                tooltip: 'When enabled, you can drag tags into a custom order in the "Filter order" tab below. Otherwise tags appear alphabetically.')
                            ->hidden(fn (Get $get) => !$get('show_in_filter'))
                            ->live()
                            ->afterStateUpdated(function ($state, $record) {
                                if ($record) {
                                    $record->update(['use_custom_tag_order' => $state]);
                                }
                            }),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('description')->wrap(),
                Tables\Columns\IconColumn::make('freetext')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\IconColumn::make('show_in_filter')
                    ->label('Show in filter bar')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('tags_count')
                                ->counts('tags')
                                ->label('# Tags'),
            ])
            ->reorderable('order_column')
            ->defaultSort('order_column')
            ->description('Tag types marked "Show in filter bar" appear as filter sections on the Browse Library page. Click the ⇅ button then drag to set the order they appear in. Note that the order saves automatically. Click Edit to control ordering of tags within a type.')
            ->headerActions([])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('resetOrder')
                        ->label('Reset to alphabetical')
                        ->icon('heroicon-o-arrow-path')
                        ->action(fn ($records) => $records->each->update(['order_column' => null]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TagsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTagTypes::route('/'),
            'edit'  => Pages\EditTagType::route('/{record}/edit'),
        ];
    }
}
