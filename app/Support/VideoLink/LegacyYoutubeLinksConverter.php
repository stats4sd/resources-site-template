<?php

namespace App\Support\VideoLink;

class LegacyYoutubeLinksConverter
{
    /**
     * Convert one locale's legacy youtube_links value — a list of ['youtube_id' => id]
     * entries or a bare single ['youtube_id' => id] — into video_links records.
     * Entries already in the new shape pass through untouched.
     */
    public static function convertLocaleEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        if (isset($entries['youtube_id'])) {
            $entries = [$entries];
        }

        $converted = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (isset($entry['url'])) {
                $converted[] = $entry;

                continue;
            }

            $videoId = $entry['youtube_id'] ?? null;
            if (! $videoId) {
                continue;
            }

            $watchUrl = "https://www.youtube.com/watch?v={$videoId}";
            $converted[] = [
                'url' => $watchUrl,
                'provider' => 'youtube',
                'embed_url' => "https://www.youtube.com/embed/{$videoId}",
                'embeddable' => true,
                'title' => null,
                'resolved_url' => $watchUrl,
            ];
        }

        return $converted;
    }

    public static function convertTranslations(mixed $translations): ?array
    {
        if (! is_array($translations)) {
            return null;
        }

        $converted = [];
        foreach ($translations as $locale => $entries) {
            $localeEntries = self::convertLocaleEntries($entries);

            if ($localeEntries) {
                $converted[$locale] = $localeEntries;
            }
        }

        return $converted ?: null;
    }
}
