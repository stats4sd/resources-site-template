<?php

namespace App\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts queries to published canonical rows only (public visibility, R1).
 *
 * A Trove is published iff its `published_at` is not null. Shadow-draft rows and
 * never-published canonical rows both have `published_at = null`, so this scope
 * hides both from any default query — including relationship queries such as
 * Collection::troves(). The scope also self-disables whenever a Filament panel is
 * the current request context (Filament::getCurrentPanel() !== null), so the admin
 * sees every version everywhere in the panel without needing to opt out explicitly.
 * Elsewhere (public web routes, console, queues, Scout imports) it still applies;
 * code in those contexts can additionally opt out via Trove::withDrafts().
 */
class PublishedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Filament::getCurrentPanel() !== null) {
            return;
        }

        $builder->whereNotNull($model->getTable() . '.published_at');
    }
}
