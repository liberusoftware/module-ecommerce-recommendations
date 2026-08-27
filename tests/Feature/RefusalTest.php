<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\Recommendations\Actions\RecordManualAffinity;
use Liberu\Ecommerce\Recommendations\Actions\RunGeneration;
use Liberu\Ecommerce\Recommendations\Actions\ServePlacement;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;

/*
 * The host could not tell "this shopper is new" from "nothing was ever
 * recorded" from "the generator has never run": all three came back as an empty
 * collection. These are the same three states, told apart.
 */

it('says no signal source is bound when nothing was ever recorded and nobody can record it', function (): void {
    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 5);

    expect($placement->isEmpty())->toBeTrue()
        ->and($placement->refusal)->toBe(RefusalReason::NoSignalSourceBound);
});

it('says no signals were recorded when a source is bound and has offered nothing', function (): void {
    bindSignalSource();

    expect((new ServePlacement())('tenant-a', 'related', 'sku-1', '', 5)->refusal)
        ->toBe(RefusalReason::NoSignalsRecorded);
});

it('says the generator has never run when signals exist and no run has succeeded', function (): void {
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'e1'));

    expect((new ServePlacement())('tenant-a', 'related', 'sku-1', '', 5)->refusal)
        ->toBe(RefusalReason::NoGenerationRun);
});

it('does not count a failed run as a run that happened', function (): void {
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'e1'));
    (new RunGeneration())('tenant-a', Strategy::ContentSimilarity);

    expect((new ServePlacement())('tenant-a', 'related', 'sku-1', '', 5)->refusal)
        ->toBe(RefusalReason::NoGenerationRun);
});

it('says the anchor has nothing when the generator ran and found nothing for it', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
    record('tenant-a', interaction(productRef: 'sku-1', sourceRef: 'e1', subjectRef: 'person-1'));
    (new RunGeneration())('tenant-a', Strategy::Popularity);

    expect((new ServePlacement())('tenant-a', 'related', 'sku-unknown', '', 5)->refusal)
        ->toBe(RefusalReason::NoAffinitiesForAnchor);
});

it('says every candidate was excluded rather than reporting no candidates', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.9);
    bindCatalogue(['sku-2' => item('sku-2', inStock: false)]);

    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 5);

    expect($placement->refusal)->toBe(RefusalReason::AllCandidatesExcluded)
        ->and($placement->candidatesExamined)->toBe(1);
});

it('never falls through from an anchored slot to popularity', function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
    record('tenant-a', interaction(productRef: 'sku-9', sourceRef: 'e1', subjectRef: 'person-1'));
    (new RunGeneration())('tenant-a', Strategy::Popularity);

    $anchored = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 5);
    $anchorless = (new ServePlacement())('tenant-a', 'trending', '', '', 5);

    expect($anchored->isEmpty())->toBeTrue()
        ->and($anchored->refusal)->toBe(RefusalReason::NoAffinitiesForAnchor)
        ->and($anchorless->productRefs())->toBe(['sku-9']);
});
