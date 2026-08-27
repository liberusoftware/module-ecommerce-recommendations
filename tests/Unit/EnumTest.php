<?php

declare(strict_types=1);

use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;

it('closes the strategy list and says what each kind of claim is about', function (): void {
    expect(Strategy::cases())->toHaveCount(4)
        ->and(Strategy::Manual->isManual())->toBeTrue()
        ->and(Strategy::Popularity->isManual())->toBeFalse()
        ->and(Strategy::Collaborative->describesSubjects())->toBeTrue()
        ->and(Strategy::Popularity->describesSubjects())->toBeTrue()
        ->and(Strategy::ContentSimilarity->describesSubjects())->toBeFalse()
        ->and(Strategy::Manual->describesSubjects())->toBeFalse();
});

it('lets a superseded claim come back and refuses a move to the state it is in', function (): void {
    expect(AffinityState::Active->canTransitionTo(AffinityState::Superseded))->toBeTrue()
        ->and(AffinityState::Active->canTransitionTo(AffinityState::Active))->toBeFalse()
        ->and(AffinityState::Superseded->canTransitionTo(AffinityState::Active))->toBeTrue()
        ->and(AffinityState::Superseded->allowed())->toBe([AffinityState::Active]);
});
