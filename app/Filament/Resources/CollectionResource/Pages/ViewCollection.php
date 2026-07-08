<?php

namespace App\Filament\Resources\CollectionResource\Pages;

use App\Filament\Resources\CollectionResource;
use App\Livewire\AllTrovesTable;
use Filament\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ViewRecord\Concerns\Translatable;
use Livewire\Attributes\On;

class ViewCollection extends ViewRecord
{

    use Translatable;

    protected static string $resource = CollectionResource::class;

    protected \Filament\Support\Enums\Width|string|null $maxContentWidth = \Filament\Support\Enums\Width::Full;

    public bool $showAllTroves = false;

    #[On('showAllTroves')]
    public function showAllTroves(): void
    {
        $this->showAllTroves = true;
    }

    #[On('hideAllTroves')]
    public function hideAllTroves(): void
    {
        $this->showAllTroves = false;
    }


    public function getHeading(): string|Htmlable
    {
        return $this->record->title;
    }

    // need to fill-form to get the activeLocale
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->troves->count() === 0) {
            $this->showAllTroves = true;
        }

        $this->fillForm();
    }

    // Infolist definition here so we can use "$this" to get the activeLocale. (Doesn't work on Resource for some reason)
    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Section::make('Collection Metadata')
                    ->description('Key information about the collection')
                    ->headerActions([
                        \Filament\Actions\Action::make('edit')
                            ->label('Edit Collection Metadata')
                            ->url(CollectionResource::getUrl('edit', ['record' => $this->record])),
                    ])
                    ->columns(7)
                    ->maxWidth('full')
                    ->schema([
                        Grid::make()
                            ->columnStart(2)
                            ->columnSpan(5)
                            ->columns(2)
                            ->maxWidth('7xl')
                            ->schema([
                                SpatieMediaLibraryImageEntry::make('cover_image')
                                    ->hiddenLabel()
                                    ->collection(fn(ViewCollection $livewire) => 'cover_image_' . $this->activeLocale)
                                    ->disk(config('media-library.disk_name'))
                                    ->width('500px')
                                    ->height('auto'),
                                TextEntry::make('description')->html(),
                            ]),
                    ]),
            ]);
    }

    // Render the infolist, then either the "all troves" picker or the relation
    // managers — mirroring the old view-collection.blade toggle in the v5 schema model.
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getInfolistContentComponent(),
                $this->showAllTroves
                    ? Livewire::make(AllTrovesTable::class, [
                        'record' => $this->record,
                        'activeLocale' => $this->activeLocale,
                    ])->key('all-troves-table')
                    : $this->getRelationManagersContentComponent(),
            ]);
    }


    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

}
