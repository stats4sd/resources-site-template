<?php

namespace App\Contracts;

use App\Services\Search\LibrarySearchRequest;
use App\Services\Search\LibrarySearchResult;
use App\Services\Search\SearchUnavailableException;

interface SearchesLibrary
{
    /**
     * @throws SearchUnavailableException
     */
    public function search(LibrarySearchRequest $request): LibrarySearchResult;
}
