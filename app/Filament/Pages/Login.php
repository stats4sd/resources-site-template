<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Illuminate\Contracts\Support\Htmlable;

class Login extends \Filament\Auth\Pages\Login
{
    /**
     * Hide the "or sign up for an account" link when registration is invite-only
     * (i.e. open registration is disabled).
     */
    public function getSubheading(): string | Htmlable | null
    {
        if (! SiteSetting::instance()->open_registration) {
            return null;
        }

        return parent::getSubheading();
    }
}
