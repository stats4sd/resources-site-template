<?php

use App\Models\Collection;
use App\Models\Trove;
use Illuminate\Console\Scheduling\Schedule;

it('schedules a nightly scout:import for both searchable models', function () {
    $scoutImportEvents = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command, 'scout:import'));

    expect($scoutImportEvents)->toHaveCount(2);

    $commands = $scoutImportEvents->pluck('command');

    expect($commands->contains(fn ($command) => str_contains($command, Trove::class)))->toBeTrue()
        ->and($commands->contains(fn ($command) => str_contains($command, Collection::class)))->toBeTrue();
});

it('only runs the scheduled scout:import when meilisearch is the active driver', function () {
    $scoutImportEvent = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'scout:import'));

    config(['scout.driver' => 'null']);
    expect($scoutImportEvent->filtersPass(app()))->toBeFalse();

    config(['scout.driver' => 'meilisearch']);
    expect($scoutImportEvent->filtersPass(app()))->toBeTrue();
});
