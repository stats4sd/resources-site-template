<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The three fixed roles this template ships with. String-backed so the value is the
 * spatie/laravel-permission role name on the `web` guard — use UserRole::Admin->value
 * for hasRole()/assignRole() so role names aren't magic strings.
 *
 * The role hierarchy is capability-only (there are no granular permissions yet):
 *   - viewer: read-only access to the admin panel (open registrants land here).
 *   - editor: the "regular user" — full CRUD/publish on content.
 *   - admin:  everything, plus user/invite management and site settings.
 *
 * The spatie foundation is laid so per-site owner-defined roles and a permission matrix
 * can come later without touching every caller — see App\Models\User helpers + policies.
 */
enum UserRole: string implements HasColor, HasLabel
{
    case Viewer = 'viewer';
    case Editor = 'editor';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Viewer => __('Viewer (read-only)'),
            self::Editor => __('Editor'),
            self::Admin => __('Administrator'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Viewer => 'gray',
            self::Editor => 'info',
            self::Admin => 'success',
        };
    }

    /**
     * A select-friendly [value => label] map for Filament role fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->getLabel()])
            ->all();
    }
}
