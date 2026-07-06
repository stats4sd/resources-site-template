<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Derived lifecycle state of an Invite (from accepted_at + expires_at). Not a column —
 * see Invite::getStatusAttribute().
 */
enum InviteStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Expired = 'expired';
    case Accepted = 'accepted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Expired => __('Expired'),
            self::Accepted => __('Accepted'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Expired => 'danger',
            self::Accepted => 'success',
        };
    }
}
