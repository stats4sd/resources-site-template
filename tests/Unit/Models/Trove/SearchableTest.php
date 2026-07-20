<?php

use App\Models\Tag;
use App\Models\Trove;
use App\Models\TroveType;

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
            ->and($array['id'])->toBe($trove->id)
            ->and($array)->not->toHaveKey('is_published');
    });

    it('strips HTML from descriptions', function () {
        $trove = publishedTrove([
            'title' => ['en' => 'Titled'],
            'description' => ['en' => '<p>Hello <strong>world</strong></p>'],
        ]);

        expect($trove->toSearchableArray()['description'])->toBe('Hello world');
    });

    it('includes tag ids and flattened multi-locale tag names', function () {
        config(['app.locales' => ['en' => 'English', 'es' => 'Spanish']]);

        $trove = publishedTrove(['title' => ['en' => 'Tagged']]);

        $gender = Tag::factory()->create(['name' => ['en' => 'Gender', 'es' => 'Género']]);
        $farming = Tag::factory()->create(['name' => ['en' => 'Farming']]);
        $trove->tags()->attach([$gender->id, $farming->id]);

        $array = $trove->fresh()->toSearchableArray();

        expect($array['tag_ids'])->toEqualCanonicalizing([$gender->id, $farming->id])
            ->and($array['tag_names'])->toContain('Gender')
            ->and($array['tag_names'])->toContain('Género')
            ->and($array['tag_names'])->toContain('Farming');
    });

    it('has empty tag attributes when the trove has no tags', function () {
        $array = publishedTrove()->toSearchableArray();

        expect($array['tag_ids'])->toBe([])
            ->and($array['tag_names'])->toBe('');
    });

    it('wraps the trove type id in an array-shaped trove_type_ids attribute', function () {
        $type = TroveType::factory()->create();
        $trove = publishedTrove(['trove_type_id' => $type->id]);

        expect($trove->toSearchableArray()['trove_type_ids'])->toBe([$type->id]);
    });

    it('has an empty trove_type_ids attribute when no type is set', function () {
        expect(publishedTrove()->toSearchableArray()['trove_type_ids'])->toBe([]);
    });

    it('lists only locales with a non-empty title translation', function () {
        config(['app.locales' => ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French']]);

        $trove = publishedTrove([
            'title' => ['en' => 'Present', 'es' => '', 'fr' => 'Présent'],
        ]);

        expect($trove->toSearchableArray()['locales'])->toBe(['en', 'fr']);
    });

    it('exposes published_at as the sort_date timestamp', function () {
        $trove = publishedTrove(['published_at' => '2026-01-02 03:04:05']);

        expect($trove->toSearchableArray()['sort_date'])
            ->toBe($trove->published_at->getTimestamp());
    });
});
