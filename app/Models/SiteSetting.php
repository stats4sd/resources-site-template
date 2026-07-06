<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = ['show_language_filter', 'open_registration', 'locales'];

    protected $casts = [
        'show_language_filter' => 'boolean',
        'open_registration' => 'boolean',
        'locales' => 'array',
    ];

    public static function instance(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'show_language_filter' => true,
            'open_registration' => false,
            'locales' => [
                ['code' => 'en', 'label' => 'English'],
            ],
        ]);
    }

    public function localesAsConfig(): array
    {
        return collect($this->locales ?? [])
            ->filter(fn ($l) => ! empty($l['code']) && ! empty($l['label']))
            ->mapWithKeys(fn ($l) => [$l['code'] => $l['label']])
            ->toArray();
    }
}
