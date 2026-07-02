<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The four user-facing lifecycle states of a Trove's working row, derived once in
 * Trove::reviewStatus() from published_id / published_at / the review flags. Every
 * consumer (list badge, tabs, publish warnings) reads this enum rather than
 * re-deriving flag combinations — that single derivation is what keeps the UI honest.
 *
 * Note "✓ reviewed" (reviewed_at) is an ORTHOGONAL marker rendered alongside the
 * badge, not a member here: a Published trove may or may not carry a review stamp.
 */
enum ReviewStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case PublishedWithPendingChanges = 'published_with_pending_changes';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::InReview => __('In review'),
            self::Published => __('Published'),
            self::PublishedWithPendingChanges => __('Published — pending changes'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Published => 'success',
            self::PublishedWithPendingChanges => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-m-pencil-square',
            self::InReview => 'heroicon-m-clipboard-document-check',
            self::Published => 'heroicon-m-check-circle',
            self::PublishedWithPendingChanges => 'heroicon-m-arrow-path',
        };
    }
}
