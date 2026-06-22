<?php

namespace App\Traits;

trait UsesCustomSearchOptions
{
    public function getSearchWithOptions(): \Closure
    {
        return function ($meiliSearch, $query, array $options = []) {
            $options['hitsPerPage'] = (int) config('scout.scout_search_limit', 500);
            $options['showRankingScore'] = true;
            return $meiliSearch->search($query, $options);
        };
    }
}