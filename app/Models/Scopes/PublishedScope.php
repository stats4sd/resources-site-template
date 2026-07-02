<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts queries to published canonical rows only (public visibility, R1).
 *
 * A Trove is published iff its `published_at` is not null. Shadow-draft rows and
 * never-published canonical rows both have `published_at = null`, so this scope
 * hides both from any default query — including relationship queries such as
 * Collection::troves(). Admin/preview code opts out via Trove::withDrafts().
 */
class PublishedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotNull($model->getTable() . '.published_at');
    }
}
