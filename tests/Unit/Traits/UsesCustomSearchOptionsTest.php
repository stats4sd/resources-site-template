<?php

use App\Traits\UsesCustomSearchOptions;

it('injects hitsPerPage and showRankingScore into the Meili search options', function () {
    $subject = new class
    {
        use UsesCustomSearchOptions;
    };

    $captured = [];
    $meili = new class($captured)
    {
        public function __construct(public array &$captured) {}

        public function search($query, array $options = [])
        {
            $this->captured = $options;

            return ['query' => $query, 'options' => $options];
        }
    };

    $closure = $subject->getSearchWithOptions();
    $result = $closure($meili, 'kittens', ['filter' => 'public = 1']);

    expect($meili->captured['hitsPerPage'])->toBe((int) config('scout.scout_search_limit', 500))
        ->and($meili->captured['hitsPerPage'])->toBe(500) // no config key set → default
        ->and($meili->captured['showRankingScore'])->toBeTrue()
        ->and($meili->captured['filter'])->toBe('public = 1') // caller options preserved
        ->and($result['query'])->toBe('kittens');
});
