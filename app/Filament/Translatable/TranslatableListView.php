<?php

namespace App\Filament\Translatable;

use Filament\Resources\Concerns\HasActiveLocaleSwitcher;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

trait TranslatableListView
{
    use Translatable;

    // Use the custom read-only content driver
    public function getFilamentTranslatableContentDriver(): ?string
    {
        return ReadOnlySpatieLaravelTranslatableContentDriver::class;
    }
}
