<?php

use App\Contracts\ResolvesVideoLinks;
use App\Filament\Resources\TroveResource\Pages\EditTrove;
use App\Models\TroveType;
use App\Models\User;
use App\Support\VideoLink\VideoLinkResult;
use Livewire\Livewire;

beforeEach(function () {
    $this->me = actingAsAdmin();

    app()->instance(ResolvesVideoLinks::class, new class implements ResolvesVideoLinks
    {
        public function resolve(string $url): VideoLinkResult
        {
            return new VideoLinkResult(
                url: $url,
                provider: 'youtube',
                embedUrl: 'https://www.youtube.com/embed/q76bMs-NwRk',
                embeddable: true,
                title: 'Resolved title',
                resolvedUrl: $url,
            );
        }
    });
});

it('closes the publish confirmation modal when the form fails validation', function () {
    $type = TroveType::factory()->create();
    $draft = draftTrove(['trove_type_id' => $type->id]);

    $component = Livewire::test(EditTrove::class, ['record' => $draft->getKey()])
        ->fillForm(['trove_type_id' => null])
        ->callAction('publish', ['confirm_publish' => true]);

    expect($component->get('mountedActions'))->toBe([]);
    expect($component->errors()->has('data.trove_type_id'))->toBeTrue();
    expect($draft->fresh()->published_at)->toBeNull();
});

it('closes the request-review modal when the form fails validation', function () {
    $type = TroveType::factory()->create();
    $draft = draftTrove(['trove_type_id' => $type->id]);
    $reviewer = User::factory()->editor()->create();

    $component = Livewire::test(EditTrove::class, ['record' => $draft->getKey()])
        ->fillForm(['trove_type_id' => null])
        ->mountAction('request_review')
        ->setActionData(['reviewer_id' => $reviewer->id])
        ->callMountedAction();

    expect($component->get('mountedActions'))->toBe([]);
    expect($draft->fresh()->review_requested_at)->toBeNull();
});
