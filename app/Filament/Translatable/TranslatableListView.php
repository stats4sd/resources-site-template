<?php

namespace App\Filament\Translatable;

use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

trait TranslatableListView
{
    use Translatable;

    // Use the custom read-only content driver
    public function getFilamentTranslatableContentDriver(): ?string
    {
        return ReadOnlySpatieLaravelTranslatableContentDriver::class;
    }
}
