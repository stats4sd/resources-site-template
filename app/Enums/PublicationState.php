<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The publication axis of a Trove's working row — one of two orthogonal facets that
 * together describe its lifecycle (the other is App\Enums\ReviewState). Derived in
 * Trove::publicationState() from published_at / published_id ALONE; it carries no
 * review information. "Is this on the public site, and are there unpublished edits?"
 * is answered here; "has anyone reviewed it?" is answered by ReviewState.
 */
enum PublicationState: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case PendingChanges = 'pending_changes';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::PendingChanges => __('Published — pending changes'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::PendingChanges => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-m-pencil-square',
            self::Published => 'heroicon-m-check-circle',
            self::PendingChanges => 'heroicon-m-arrow-path',
        };
    }
}
