<?php

use App\Enums\ReviewState;

it('has the expected cases and values', function () {
    expect(ReviewState::None->value)->toBe('none')
        ->and(ReviewState::InReview->value)->toBe('in_review')
        ->and(ReviewState::Reviewed->value)->toBe('reviewed');
});

it('maps each case to a label', function () {
    expect(ReviewState::None->getLabel())->toBe('Not reviewed')
        ->and(ReviewState::InReview->getLabel())->toBe('In review')
        ->and(ReviewState::Reviewed->getLabel())->toBe('Reviewed');
});

it('maps each case to a colour', function () {
    expect(ReviewState::None->getColor())->toBe('gray')
        ->and(ReviewState::InReview->getColor())->toBe('warning')
        ->and(ReviewState::Reviewed->getColor())->toBe('success');
});

it('maps each case to an icon', function () {
    expect(ReviewState::None->getIcon())->toBe('heroicon-m-minus-circle')
        ->and(ReviewState::InReview->getIcon())->toBe('heroicon-m-clipboard-document-check')
        ->and(ReviewState::Reviewed->getIcon())->toBe('heroicon-m-check-badge');
});
