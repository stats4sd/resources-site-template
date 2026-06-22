<?php

namespace App\Filament\Resources;

use App\Filament\Translatable\Form\TranslatableComboField;
use Filament\Forms;
use Filament\Tables;
use App\Models\TagType;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Forms\Components\Split;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Concerns\Translatable;
use App\Filament\Resources\TagTypeResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\TagTypeResource\RelationManagers;

class TagTypeResource extends Resource
{
    use Translatable;

    protected static ?string $model = TagType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
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

                Forms\Components\Section::make('')
                    ->schema([
                        Forms\Components\Checkbox::make('freetext')
                            ->label('Can the user add new tags of this type during Trove upload?')
                            ->hintIcon(
                                icon: 'heroicon-m-question-mark-circle',
                                tooltip: 'Most types should not have this enabled, to prevent accidental duplication / mistyping during Trove upload. But this option is available for e.g. "Keywords" or "Authors", where new tags are likely to be needed.'),
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
                // Tables\Columns\TextColumn::make('tags_count')
                //                 ->counts('tags')
                //                 ->label('# Tags')
                //                 ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTagTypes::route('/'),
        ];
    }
}
