<?php

use App\Models\Trove;

describe('shouldBeSearchable', function () {
    it('is true only for a published canonical row', function () {
        $canonical = publishedTrove();

        expect($canonical->shouldBeSearchable())->toBeTrue();
    });

    it('is false for a never-published canonical', function () {
        expect(draftTrove()->shouldBeSearchable())->toBeFalse();
    });

    it('is false for a shadow draft even if it carried a publish date', function () {
        $canonical = publishedTrove();
        $draft = Trove::withoutSyncingToSearch(fn () => Trove::factory()
            ->draftOf($canonical)
            ->create());

        expect($draft->shouldBeSearchable())->toBeFalse();
    });
});

describe('toSearchableArray', function () {
    it('aggregates unique non-empty titles/descriptions across configured locales', function () {
        config(['app.locales' => ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French']]);

        $trove = publishedTrove([
            'title' => ['en' => 'Data Analysis', 'es' => 'Análisis', 'fr' => 'Data Analysis'],
            'description' => ['en' => 'English desc', 'es' => 'Spanish desc'],
        ]);

        $array = $trove->toSearchableArray();

        // 'Data Analysis' appears twice (en + fr) but is deduped.
        expect($array['title'])->toBe('Data Analysis Análisis')
            ->and($array['description'])->toBe('English desc Spanish desc')
            ->and($array['is_published'])->toBe(1)
            ->and($array['id'])->toBe($trove->id);
    });

    it('strips HTML from descriptions', function () {
        $trove = publishedTrove([
            'title' => ['en' => 'Titled'],
            'description' => ['en' => '<p>Hello <strong>world</strong></p>'],
        ]);

        expect($trove->toSearchableArray()['description'])->toBe('Hello world');
    });

    it('reports is_published as 0 for an unpublished trove', function () {
        expect(draftTrove()->toSearchableArray()['is_published'])->toBe(0);
    });
});
