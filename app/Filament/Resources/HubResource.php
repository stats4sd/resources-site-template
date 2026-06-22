<?php

namespace App\Filament\Resources;

use App\Models\Hub;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\HubResource\Pages;
use Filament\Resources\Concerns\Translatable;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\HubResource\RelationManagers;
use App\Filament\Translatable\Form\TranslatableComboField;

class HubResource extends Resource
{
    use Translatable;
    
    protected static ?string $model = Hub::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TranslatableComboField::make('name')
                    ->required()
                    ->icon('heroicon-o-exclamation-circle')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Name')
                    ->description('Enter the name of the hub')
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class),

                TranslatableComboField::make('description')
                    ->required()
                    ->icon('heroicon-o-exclamation-circle')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Description')
                    ->description('Enter a description of the hub')
                    ->columns(3)
                    ->childField(Forms\Components\TextInput::class),

                Section::make('Themes')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->icon('heroicon-s-tag')
                    ->iconColor('primary')
                    ->description('Select the themes covered by this hub')
                    ->schema([
                        Forms\Components\Select::make('theme_tags')
                        ->label('Themes')
                        ->multiple()
                        ->relationship(
                            name: 'tags',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn(Builder $query) => $query->where('type_id', 4))
                        ->preload()
                    ]),

                Section::make('Does the hub have a custom designed frontend?')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->icon('heroicon-s-paint-brush')
                    ->iconColor('primary')
                    ->schema([
                        Radio::make('has_custom_frontend')
                            ->label('')
                            ->options([
                                1 => 'Yes',
                                0 => 'No - use the generic template'
                            ])
                    ]),

                Forms\Components\Hidden::make('uploader_id')
                    ->default(Auth::id()),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('themeTags.name')
                    ->label('Themes')
                    ->badge()
                    ->wrap(),
                Tables\Columns\IconColumn::make('has_custom_frontend')
                    ->boolean()
                    ->label('Custom Frontend')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Upload Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Created By')
                    ->sortable(),
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
            'index' => Pages\ListHubs::route('/'),
            'create' => Pages\CreateHub::route('/create'),
            'edit' => Pages\EditHub::route('/{record}/edit'),
        ];
    }
}
