<?php

use App\Enums\PublicationState;

// Change-detector: pins the label/color/icon maps so an accidental case rename or
// colour swap fails loudly (these drive the admin badge UI).

it('has the expected cases and values', function () {
    expect(PublicationState::Draft->value)->toBe('draft')
        ->and(PublicationState::Published->value)->toBe('published')
        ->and(PublicationState::PendingChanges->value)->toBe('pending_changes');
});

it('maps each case to a label', function () {
    expect(PublicationState::Draft->getLabel())->toBe('Draft')
        ->and(PublicationState::Published->getLabel())->toBe('Published')
        ->and(PublicationState::PendingChanges->getLabel())->toBe('Published - pending changes');
});

it('maps each case to a colour', function () {
    expect(PublicationState::Draft->getColor())->toBe('gray')
        ->and(PublicationState::Published->getColor())->toBe('success')
        ->and(PublicationState::PendingChanges->getColor())->toBe('info');
});

it('maps each case to an icon', function () {
    expect(PublicationState::Draft->getIcon())->toBe('heroicon-m-pencil-square')
        ->and(PublicationState::Published->getIcon())->toBe('heroicon-m-check-circle')
        ->and(PublicationState::PendingChanges->getIcon())->toBe('heroicon-m-arrow-path');
});
