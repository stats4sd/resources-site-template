<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\TroveType;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Concerns\Translatable;
use App\Filament\Resources\TroveTypeResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Translatable\Form\TranslatableComboField;
use App\Filament\Resources\TroveTypeResource\RelationManagers;

class TroveTypeResource extends Resource
{
    use Translatable;

    protected static ?string $model = TroveType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // protected static ?string $navigationLabel = 'Trove Types';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TranslatableComboField::make('label')
                    ->label(__('Trove Type'))
                    ->description('Enter the name of the trove type. E.g. "video", "presentation", "ODK Form Template", "R Project"')
                    ->icon('heroicon-s-tag')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class)
                    ->required(),
                Forms\Components\Hidden::make('order')
                    ->default(fn() => (TroveType::max('order') ?? 0) + 1),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label'),
                Tables\Columns\TextColumn::make('troves_count')
                    ->counts('troves')
                    ->label('# Troves')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading(''),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->modalHeading(''),
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
            'index' => Pages\ListTroveTypes::route('/'),
        ];
    }
}
