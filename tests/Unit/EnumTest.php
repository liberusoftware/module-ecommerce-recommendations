<?php

declare(strict_types=1);

use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\ExclusionReason;
use Liberu\Ecommerce\Recommendations\Enums\RunState;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;

it('closes the strategy list and says what each kind of claim is about', function (): void {
    expect(Strategy::generated())->toBe([Strategy::Collaborative, Strategy::ContentSimilarity, Strategy::Popularity])
        ->and(Strategy::Manual->isManual())->toBeTrue()
        ->and(Strategy::Popularity->isManual())->toBeFalse()
        ->and(Strategy::Popularity->isAnchored())->toBeFalse()
        ->and(Strategy::Collaborative->isAnchored())->toBeTrue()
        ->and(Strategy::Collaborative->describesSubjects())->toBeTrue()
        ->and(Strategy::Popularity->describesSubjects())->toBeTrue()
        ->and(Strategy::ContentSimilarity->describesSubjects())->toBeFalse()
        ->and(Strategy::Manual->describesSubjects())->toBeFalse();
});

it('evidences a co-purchase only from a purchase', function (): void {
    expect(SignalKind::Purchase->evidencesCoPurchase())->toBeTrue()
        ->and(SignalKind::View->evidencesCoPurchase())->toBeFalse();
});

it('lets a superseded claim come back and refuses a move to the state it is in', function (): void {
    expect(AffinityState::Active->canTransitionTo(AffinityState::Superseded))->toBeTrue()
        ->and(AffinityState::Active->canTransitionTo(AffinityState::Active))->toBeFalse()
        ->and(AffinityState::Superseded->canTransitionTo(AffinityState::Active))->toBeTrue()
        ->and(AffinityState::Superseded->allowed())->toBe([AffinityState::Active]);
});

it('supersedes only from a run that finished successfully', function (): void {
    expect(RunState::Succeeded->supersedes())->toBeTrue()
        ->and(RunState::Running->supersedes())->toBeFalse()
        ->and(RunState::Failed->supersedes())->toBeFalse();
});

it('knows which exclusions need the catalogue seam', function (): void {
    expect(ExclusionReason::OutOfStock->needsCatalogue())->toBeTrue()
        ->and(ExclusionReason::Suppressed->needsCatalogue())->toBeTrue()
        ->and(ExclusionReason::UnresolvableRef->needsCatalogue())->toBeTrue()
        ->and(ExclusionReason::AlreadyPurchased->needsCatalogue())->toBeFalse()
        ->and(ExclusionReason::IsAnchor->needsCatalogue())->toBeFalse()
        ->and(ExclusionReason::AlreadyInCart->needsCatalogue())->toBeFalse();
});
