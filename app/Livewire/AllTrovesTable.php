<?php

namespace App\Livewire;

use App\Filament\Resources\CollectionResource;
use App\Filament\Resources\TroveResource;
use App\Models\Trove;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LaraZeus\SpatieTranslatable\SpatieTranslatableContentDriver;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class AllTrovesTable extends Component implements HasActions, HasForms, HasTable
{
    use EvaluatesClosures;
    use InteractsWithActions;
    use InteractsWithForms;
    // InteractsWithRecord is kept only for the $record property + getRecord()/hasRecord()
    // (the pinned Collection). Its action-default overrides are page-context (they resolve to
    // that Collection or call parent::) and are wrong for per-row table actions on Troves —
    // defer every colliding method to InteractsWithActions.
    use InteractsWithRecord {
        InteractsWithActions::afterActionCalled insteadof InteractsWithRecord;
        InteractsWithActions::getMountedActionSchemaModel insteadof InteractsWithRecord;
        InteractsWithActions::getDefaultActionRecord insteadof InteractsWithRecord;
        InteractsWithActions::getDefaultActionRecordTitle insteadof InteractsWithRecord;
        InteractsWithActions::getDefaultActionSuccessRedirectUrl insteadof InteractsWithRecord;
    }

    use InteractsWithTable;

    protected static string $resource = CollectionResource::class;

    #[Reactive]
    public string $activeLocale;

    /**
     * A plain Livewire component (not a Filament-native one) loses the current panel
     * on subsequent /livewire/update requests, which would re-enable PublishedScope
     * mid-interaction. Pin it here so unpublished/draft troves stay visible across
     * search/filter/paginate.
     */
    public function booted(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function render()
    {
        return view('livewire.all-troves-table');
    }

    /**
     * From ListRecords
     */
    public static function getResource(): string
    {
        return static::$resource;
    }

    public function table(Table $table): Table
    {
        return $table
            ->searchable()
            ->headerActions([
                Action::make('hide_all_troves')
                    ->label('Show Troves in Collection')
                    ->action(fn (Component $livewire) => $livewire->dispatch('hideAllTroves')),
            ])
            ->query(fn (): Builder => Trove::query()->workingVersions())
            ->heading('All Troves')
            ->description('Select Troves to add to this Collection')
            ->columns(TroveResource::getTableColumns())
            ->filters(TroveResource::getTableFilters())
            ->filtersTriggerAction(fn ($action) => $action->button()->label('Filters'))
            ->filtersLayout(fn () => FiltersLayout::AboveContentCollapsible)
            ->deferFilters(false)
            ->recordActions([

                Action::make('attach_trove')
                    ->label('Add Trove to Collection')
                    ->color('success')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (Trove $record) => ! $record->collections->contains($this->getRecord()))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Trove $record) {
                        $this->getRecord()->troves()->attach($record);
                        $this->getRecord()->searchable();
                        Notification::make()
                            ->title('Trove Added Successfully')
                            ->success()
                            ->send();
                        $this->resetTable();
                    }),
                Action::make('detach_trove')
                    ->icon('heroicon-o-minus')
                    ->color('danger')
                    ->label('Remove Trove from Collection')
                    ->visible(fn (Trove $record) => $record->collections->contains($this->getRecord()))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Trove $record) {
                        $this->getRecord()->troves()->detach($record);
                        $this->getRecord()->searchable();
                        Notification::make()
                            ->title('Trove Removed Successfully')
                            ->success()
                            ->send();
                        $this->resetTable();
                    }),
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
            ])
            ->recordUrl(fn (Trove $record) => url('/resources/'.$record->slug))
            ->toolbarActions([
                BulkAction::make('attach')
                    ->label('Add Trove(s) to Collection')
                    ->action(function (Collection $records) {
                        $this->getRecord()->troves()->syncWithoutDetaching($records);
                        $this->getRecord()->searchable();
                    }),
            ]);
    }

    /*
     * TRANSLATABLE STUFF
     */

    public function getActiveTableLocale(): ?string
    {
        return $this->activeLocale;
    }

    public function getTranslatableLocales(): array
    {
        return static::getResource()::getTranslatableLocales();
    }

    public function getActiveFormsLocale(): ?string
    {
        if (! in_array($this->activeLocale, $this->getTranslatableLocales())) {
            return null;
        }

        return $this->activeLocale;
    }

    public function getActiveActionsLocale(): ?string
    {
        return $this->activeLocale;
    }

    /**
     * @return class-string<TranslatableContentDriver> | null
     */
    public function getFilamentTranslatableContentDriver(): ?string
    {
        return SpatieTranslatableContentDriver::class;
    }
}
