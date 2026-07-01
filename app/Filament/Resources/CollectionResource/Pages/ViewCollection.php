<?php

namespace App\Filament\Resources\CollectionResource\Pages;

use App\Filament\Resources\CollectionResource;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

class ViewCollection extends ViewRecord
{

    use ViewRecord\Concerns\Translatable;

    protected static string $resource = CollectionResource::class;
    protected static string $view = 'filament.pages.view-collection';

    protected ?string $maxContentWidth = 'full';

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
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->columns(1)
            ->schema([
                Section::make('Collection Metadata')
                    ->description('Key information about the collection')
                    ->headerActions([
                        \Filament\Infolists\Components\Actions\Action::make('edit')
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
                                TextEntry::make('description'),
                            ]),
                    ]),
            ]);
    }


    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
        ];
    }

}
