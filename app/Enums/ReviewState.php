<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The review axis of a Trove's working row — orthogonal to App\Enums\PublicationState.
 * Derived in Trove::reviewState() from the review columns ALONE (review_requested_at /
 * reviewed_at); it carries no publication information. "Reviewed" is a first-class member
 * of its own axis (it used to be an orphaned "orthogonal ✓ marker" bolted onto the old
 * fused ReviewStatus enum). Precedence lives only WITHIN this axis: reviewed_at set means
 * Reviewed even if a stale review_requested_at lingers.
 */
enum ReviewState: string implements HasColor, HasIcon, HasLabel
{
    case None = 'none';
    case InReview = 'in_review';
    case Reviewed = 'reviewed';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => __('Not reviewed'),
            self::InReview => __('In review'),
            self::Reviewed => __('Reviewed'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::InReview => 'warning',
            self::Reviewed => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::None => 'heroicon-m-minus-circle',
            self::InReview => 'heroicon-m-clipboard-document-check',
            self::Reviewed => 'heroicon-m-check-badge',
        };
    }
}
