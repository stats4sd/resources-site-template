<?php

namespace App\Filament\Resources\TagTypeResource\RelationManagers;

use App\Filament\Translatable\Form\TranslatableComboField;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $title = 'Filter order';

    protected static ?string $modelLabel = 'tag';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) $ownerRecord->use_custom_tag_order;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TranslatableComboField::make('name')
                    ->icon('heroicon-s-tag')
                    ->iconColor('primary')
                    ->extraAttributes(['class' => 'grey-box'])
                    ->label('Tag name')
                    ->childField(Forms\Components\TextInput::class)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('order_column')
            ->defaultSort('order_column')
            ->description('Click the ⇅ button, then drag to set the order tags appear in the filter bar. Note that the order saves automatically. Disable "Use custom tag order?" above to revert to alphabetical.')
            ->headerActions([
                CreateAction::make(),
                Action::make('resetAllOrder')
                    ->label('Reset all to alphabetical')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(fn () => $this->getOwnerRecord()->tags()->update(['order_column' => null])),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
}
