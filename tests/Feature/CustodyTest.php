<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\Recommendations\Actions\RecordManualAffinity;
use Liberu\Ecommerce\Recommendations\Actions\RunGeneration;
use Liberu\Ecommerce\Recommendations\Actions\ServePlacement;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\SignalKind;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\AffinityEvent;
use Liberu\Ecommerce\Recommendations\Models\GenerationRun;
use Liberu\Ecommerce\Recommendations\Models\Placement;
use Liberu\Ecommerce\Recommendations\Models\PlacementEntry;
use Liberu\Ecommerce\Recommendations\Models\Signal;
use Liberu\Ecommerce\Recommendations\Policies\CustodyPolicy;

beforeEach(function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);
});

it('gives a second merchant its own signal for a deliberately identical reference', function (): void {
    record('tenant-a', interaction(subjectRef: 'person-1', sourceRef: 'event-1'));
    record('tenant-b', interaction(subjectRef: 'person-1', sourceRef: 'event-1'));

    expect(Signal::query()->count())->toBe(2)
        ->and(Signal::query()->where('tenant_id', 'tenant-a')->count())->toBe(1)
        ->and(Signal::query()->where('tenant_id', 'tenant-b')->count())->toBe(1);
});

it('never lets one merchant traffic rank another merchant products', function (): void {
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-2']);
    order('tenant-b', 'person-2', 'order-1', ['sku-1', 'sku-3']);

    (new RunGeneration())('tenant-a', Strategy::Collaborative);

    expect(Affinity::query()->where('tenant_id', 'tenant-a')->pluck('to_ref')->all())->toBe(['sku-2', 'sku-1'])
        ->and(Affinity::query()->where('tenant_id', 'tenant-b')->count())->toBe(0)
        ->and((new ServePlacement())('tenant-a', 'related', 'sku-1', '', 5)->productRefs())->toBe(['sku-2']);
});

it('stamps every row with the tenant that owns it', function (): void {
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-2']);
    (new RunGeneration())('tenant-a', Strategy::Collaborative);
    (new ServePlacement())('tenant-a', 'related', 'sku-1', 'person-1', 5);

    foreach ([Signal::class, Affinity::class, AffinityEvent::class, GenerationRun::class, Placement::class, PlacementEntry::class] as $model) {
        expect($model::query()->where('tenant_id', 'tenant-a')->count())->toBeGreaterThan(0)
            ->and($model::query()->whereNull('tenant_id')->count())->toBe(0)
            ->and($model::query()->where('tenant_id', '!=', 'tenant-a')->count())->toBe(0);
    }
});

it('restates the tenant on a loaded relation, excluding a row planted under another', function (): void {
    $recorded = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2');
    $affinity = Affinity::query()->findOrFail($recorded->id);

    AffinityEvent::query()->create([
        'tenant_id' => 'tenant-b',
        'affinity_id' => $affinity->id,
        'sequence' => 500,
        'from_state' => AffinityState::Active,
        'to_state' => AffinityState::Superseded,
        'occurred_at' => Carbon::now(),
    ]);

    expect(AffinityEvent::query()->where('affinity_id', $affinity->id)->count())->toBe(2)
        ->and($affinity->events()->count())->toBe(1);
});

it('drops the restatement when there is no tenant to restate, so withCount does not report zero', function (): void {
    order('tenant-a', 'person-1', 'order-1', ['sku-1', 'sku-2']);
    (new RunGeneration())('tenant-a', Strategy::Collaborative);
    (new ServePlacement())('tenant-a', 'related', 'sku-1', 'person-1', 5);

    $counted = Affinity::query()->withCount('events')->firstOrFail();
    $run = GenerationRun::query()->withCount('affinities')->firstOrFail();
    $placement = Placement::query()->withCount('entries')->firstOrFail();

    // The fresh instance the relation is built from has no tenant, so the guard
    // stands down and the count is the real one rather than nothing at all.
    expect($counted->getAttribute('events_count'))->toBe(1)
        ->and($run->getAttribute('affinities_count'))->toBe(2)
        ->and($placement->getAttribute('entries_count'))->toBe(1)
        ->and(Affinity::query()->whereHas('events')->count())->toBe(2);
});

it('never reports zero because a null tenant became an empty string', function (): void {
    $recorded = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2');
    $affinity = Affinity::query()->findOrFail($recorded->id);
    $fresh = new Affinity();

    expect($fresh->getAttribute('tenant_id'))->toBeNull()
        ->and($fresh->events()->toBase()->toSql())->not->toContain('tenant_id')
        ->and($affinity->events()->toBase()->toSql())->toContain('tenant_id');
});

it('answers standing from the reference in hand, never from a role name', function (): void {
    $recorded = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2');
    $affinity = Affinity::query()->findOrFail($recorded->id);
    $served = (new ServePlacement())('tenant-a', 'related', 'sku-1', 'person-1', 5);
    $placement = Placement::query()->findOrFail($served->id);

    expect(CustodyPolicy::ownsAffinity($affinity, 'tenant-a'))->toBeTrue()
        ->and(CustodyPolicy::ownsAffinity($affinity, 'tenant-b'))->toBeFalse()
        ->and(CustodyPolicy::ownsPlacement($placement, 'tenant-a'))->toBeTrue()
        ->and(CustodyPolicy::ownsPlacement($placement, 'tenant-b'))->toBeFalse()
        ->and(CustodyPolicy::subjectMayRead($placement, 'tenant-a', 'person-1'))->toBeTrue()
        ->and(CustodyPolicy::subjectMayRead($placement, 'tenant-a', 'person-2'))->toBeFalse()
        ->and(CustodyPolicy::subjectMayRead($placement, 'tenant-b', 'person-1'))->toBeFalse()
        ->and(CustodyPolicy::subjectMayRead($placement, 'tenant-a', ''))->toBeFalse();
});

it('does not let one merchant exclusions shape another merchant placement', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2');
    (new RecordManualAffinity())('tenant-b', 'sku-1', 'sku-2');
    record('tenant-a', interaction(productRef: 'sku-2', kind: SignalKind::Purchase, sourceRef: 'p1', subjectRef: 'person-1'));

    expect((new ServePlacement())('tenant-a', 'related', 'sku-1', 'person-1', 5)->isEmpty())->toBeTrue()
        ->and((new ServePlacement())('tenant-b', 'related', 'sku-1', 'person-1', 5)->productRefs())->toBe(['sku-2']);
});
