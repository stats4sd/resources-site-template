<?php

namespace App\Filament\Resources\TagTypeResource\Pages;

use App\Filament\Resources\TagTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class EditTagType extends EditRecord
{
    use Translatable;

    protected static string $resource = TagTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
