<?php

namespace App\Filament\Resources;

use App\Filament\Translatable\Form\TranslatableComboField;
use App\Models\Tag;
use Filament\Forms;
use Filament\Tables;
use App\Models\TagType;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\TagResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\TagResource\RelationManagers;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class TagResource extends Resource
{
    use Translatable;

    protected static ?string $model = Tag::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TranslatableComboField::make('name')
                    ->required()
                    ->icon('heroicon-s-tag')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Name')
                    ->description('Enter the name of the tag')
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class),


                Forms\Components\Select::make('type_id')
                    ->relationship('tagType', 'label')
                    ->getOptionLabelFromRecordUsing(fn($record, $livewire) => $record->getTranslation('label', 'en'))
                    ->required(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name->' . app()->getLocale())
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('tagType.label'),
                Tables\Columns\TextColumn::make('troves_count')
                    ->label('# of troves')
                    ->counts('troves')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tagType')
                    ->relationship('tagType', 'label')
                    ->getOptionLabelFromRecordUsing(fn($record, $livewire) => $record->getTranslation('label', 'en')),
            ])
            ->recordActions([
                EditAction::make()
                ->modalHeading(''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => Pages\ListTags::route('/'),
        ];
    }
}
