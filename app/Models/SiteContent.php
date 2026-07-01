<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class SiteContent extends Model
{
    use HasTranslations;

    protected $fillable = ['key', 'value'];

    public array $translatable = ['value'];

    public static function get(string $key, ?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $record = static::where('key', $key)->first();
        return $record?->getTranslation('value', $locale, false) ?: null;
    }
}
