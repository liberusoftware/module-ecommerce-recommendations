<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\Recommendations\Actions\RecordManualAffinity;
use Liberu\Ecommerce\Recommendations\Actions\RunGeneration;
use Liberu\Ecommerce\Recommendations\Actions\ServePlacement;
use Liberu\Ecommerce\Recommendations\Enums\ExclusionReason;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Events\PlacementServed;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\Placement;
use Liberu\Ecommerce\Recommendations\Models\PlacementEntry;
use Liberu\Ecommerce\Recommendations\Queries\ExplainPlacement;
use Liberu\Ecommerce\Recommendations\Queries\PlanPlacement;

beforeEach(function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
});

it('records the placement before it returns it, entries and all', function (): void {
    Event::fake();
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.4);
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-3', 0.9);

    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', 'person-1', 5);
    $row = Placement::query()->findOrFail($placement->id);

    expect($placement->productRefs())->toBe(['sku-3', 'sku-2'])
        ->and($row->returned)->toBe(2)
        ->and($row->requested)->toBe(5)
        ->and($row->candidates_examined)->toBe(2)
        ->and($row->refusal)->toBeNull()
        ->and($row->catalogue_checked)->toBeFalse()
        ->and($row->cart_checked)->toBeFalse()
        ->and($row->entries()->count())->toBe(2)
        ->and($row->entries()->orderBy('position')->pluck('product_ref')->all())->toBe(['sku-3', 'sku-2']);

    Event::assertDispatched(PlacementServed::class, static fn (PlacementServed $e): bool => $e->returned === 2 && $e->refusal === null);
});

it('puts a curated claim above a computed one and says which produced each entry', function (): void {
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-9']);
    (new RunGeneration())('tenant-a', Strategy::Collaborative);
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.01);

    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 5);

    expect($placement->productRefs())->toBe(['sku-2', 'sku-9'])
        ->and($placement->strategyCounts())->toBe(['manual' => 1, 'collaborative' => 1])
        ->and($placement->shown[0]->strategy)->toBe(Strategy::Manual);
});

it('applies one exclusion list once and counts every removal', function (): void {
    foreach (['sku-2', 'sku-3', 'sku-4', 'sku-5', 'sku-6'] as $ref) {
        (new RecordManualAffinity())('tenant-a', 'sku-1', $ref, 0.5);
    }
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-7', 0.5);

    record('tenant-a', interaction(productRef: 'sku-2', kind: SignalKind::Purchase, sourceRef: 'p1', subjectRef: 'person-1'));
    bindShopper(['sku-3']);
    bindCatalogue([
        'sku-4' => item('sku-4', inStock: false),
        'sku-5' => item('sku-5', suppressed: true),
        'sku-7' => item('sku-7'),
    ]);

    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', 'person-1', 10);

    expect($placement->productRefs())->toBe(['sku-7'])
        ->and($placement->exclusionCounts())->toBe([
            'already_purchased' => 1,
            'already_in_cart' => 1,
            'out_of_stock' => 1,
            'suppressed' => 1,
            'unresolvable_ref' => 1,
        ])
        ->and($placement->catalogueChecked)->toBeTrue()
        ->and($placement->cartChecked)->toBeTrue()
        ->and(PlacementEntry::query()->whereNull('position')->count())->toBe(5);
});

it('reports a reference the catalogue does not know rather than quietly shortening the list', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-gone', 0.9);
    bindCatalogue([]);

    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 10);

    expect($placement->isEmpty())->toBeTrue()
        ->and($placement->refusal)->toBe(RefusalReason::AllCandidatesExcluded)
        ->and($placement->excluded[0]->excludedFor)->toBe(ExclusionReason::UnresolvableRef);
});

it('checks nothing the catalogue controls when no catalogue is bound, and drops no reference for it', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-gone', 0.9);

    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 10);

    expect($placement->productRefs())->toBe(['sku-gone'])
        ->and($placement->catalogueChecked)->toBeFalse();
});

it('never recommends the anchor back to itself', function (): void {
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-2']);
    (new RunGeneration())('tenant-a', Strategy::Collaborative);

    Affinity::query()
        ->where('to_ref', 'sku-2')->firstOrFail()->forceFill(['to_ref' => 'sku-1'])->save();

    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 10);

    expect($placement->isEmpty())->toBeTrue()
        ->and($placement->excluded[0]->excludedFor)->toBe(ExclusionReason::IsAnchor);
});

it('asks the cart only when there is a shopper to ask about', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.5);
    $shopper = bindShopper(['sku-2']);

    $anonymous = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 10);

    expect($anonymous->productRefs())->toBe(['sku-2'])
        ->and($anonymous->cartChecked)->toBeFalse()
        ->and($shopper->asked)->toBe(0);
});

it('bounds what it reads and still honours the requested count', function (): void {
    Config::set('recommendations.serve.candidate_overfetch', 1);

    foreach (range(1, 9) as $index) {
        (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-'.$index, $index / 10);
    }

    $placement = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 2);

    expect($placement->shown)->toHaveCount(2)
        ->and($placement->candidatesExamined)->toBe(2)
        ->and($placement->productRefs())->toBe(['sku-9', 'sku-8']);
});

it('treats a request for nothing as a request for one', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.5);

    expect((new PlanPlacement())('tenant-a', 'related', 'sku-1', '', 0)->requested)->toBe(1);
});

it('reads a stored placement back, months later, for the merchant that owns it', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.6);
    bindCatalogue(['sku-2' => item('sku-2', inStock: false)]);
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-3', 0.4);
    bindCatalogue(['sku-3' => item('sku-3')]);

    $served = (new ServePlacement())('tenant-a', 'related', 'sku-1', 'person-1', 4, 99);
    $explained = (new ExplainPlacement())('tenant-a', (int) $served->id);

    expect($explained?->productRefs())->toBe(['sku-3'])
        ->and($explained?->seed)->toBe(99)
        ->and($explained?->requested)->toBe(4)
        ->and($explained?->subjectRef)->toBe('person-1')
        ->and($explained?->excluded)->toHaveCount(1)
        ->and($explained?->excluded[0]->excludedFor)->toBe(ExclusionReason::UnresolvableRef)
        ->and($explained?->shown[0]->strategy)->toBe(Strategy::Manual)
        ->and($explained?->shown[0]->rawScore)->toBe(0.4);
});

it('explains nothing to a merchant that does not own the placement, or about a row that is not there', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.6);
    $served = (new ServePlacement())('tenant-a', 'related', 'sku-1', '', 4);

    expect((new ExplainPlacement())('tenant-b', (int) $served->id))->toBeNull()
        ->and((new ExplainPlacement())('tenant-a', 9999))->toBeNull();
});
