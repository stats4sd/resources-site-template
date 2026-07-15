<?php

namespace App\Filament\Resources\CollectionResource\RelationManagers;

use App\Filament\Resources\TroveResource;
use App\Models\Trove;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\RelationManagers\Concerns\Translatable;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class TrovesRelationManager extends RelationManager
{
    use Translatable;

    #[Reactive]
    public ?string $activeLocale = null;

    protected static string $relationship = 'troves';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    /**
     * @throws \Exception
     */
    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('show_all_troves')
                    ->label('Show All Troves')
                    ->action(fn (Component $livewire) => $livewire->dispatch('showAllTroves')),
            ])
            ->recordTitleAttribute('title')
            ->searchable()
            ->deferFilters(false)
            ->heading('Troves in this Collection')
            ->columns(TroveResource::getTableColumns())
            ->filters(TroveResource::getTableFilters())
            ->filtersTriggerAction(fn ($action) => $action->button()->label('Filters'))
            ->filtersLayout(fn () => FiltersLayout::AboveContentCollapsible)
            ->recordActions([
                Action::make('preview_trove')
                    ->label('Preview on Front-end')
                    ->icon('heroicon-o-eye')
                    ->url(function (Trove $record) {
                        return $record->is_published
                            ? url('/resources/'.$record->slug)
                            : url('/resources/preview/'.$record->slug);
                    })
                    ->openUrlInNewTab()
                    ->action(null)
                    ->link(),
                DetachAction::make()
                    ->label('Remove trove from collection')
                    ->modalHeading('Remove trove from collection')
                    ->successNotificationTitle('Trove removed from collection')
                    ->after(fn () => $this->getOwnerRecord()->searchable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                    DetachBulkAction::make()
                        ->label('Remove troves from collection')
                        ->after(fn () => $this->getOwnerRecord()->searchable()),
                ]),
            ])
            ->recordUrl(fn (Trove $record) => url('/resources/'.$record->slug))
            ->emptyStateDescription(
                'Use the "Show All Troves" button above to find troves to add to the collection.'
            );
    }
}
