<?php

namespace App\Filament\Resources\TroveResource\Concerns;

use App\Contracts\ResolvesVideoLinks;
use Throwable;

trait ResolvesVideoLinkFormData
{
    /**
     * The form resolves URLs on blur; this save-time pass covers rows where that
     * round-trip never fired (paste-then-save) or the URL changed after resolution.
     */
    protected function resolveVideoLinkFormData(array $data): array
    {
        if (empty($data['video_links'])) {
            return $data;
        }

        if (! is_array($data['video_links'])) {
            return $data;
        }

        $resolver = app(ResolvesVideoLinks::class);

        foreach ($data['video_links'] as $locale => $rows) {
            if (! is_array($rows)) {
                continue;
            }

            $resolvedRows = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $url = trim((string) ($row['url'] ?? ''));
                if ($url === '') {
                    continue;
                }

                if (($row['resolved_url'] ?? null) !== $url) {
                    try {
                        $row = array_merge($row, $resolver->resolve($url)->toArray());
                    } catch (Throwable) {
                        $row = array_merge($row, ['embeddable' => false, 'embed_url' => null, 'resolved_url' => $url]);
                    }
                }

                $resolvedRows[] = $row;
            }

            $data['video_links'][$locale] = $resolvedRows;
        }

        return $data;
    }
}
