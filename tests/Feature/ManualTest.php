<?php

declare(strict_types=1);

use Liberu\Ecommerce\Recommendations\Actions\RecordManualAffinity;
use Liberu\Ecommerce\Recommendations\Actions\WithdrawAffinity;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Exceptions\ScoreOutOfRange;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Queries\ListAffinities;

it('records a merchandiser claim into the same table the generator writes', function (): void {
    $outcome = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.75);
    $affinity = Affinity::query()->findOrFail($outcome->id);

    expect($outcome->happened())->toBeTrue()
        ->and($affinity->strategy)->toBe(Strategy::Manual)
        ->and($affinity->run_id)->toBeNull()
        ->and($affinity->ratio())->toBe(0.75)
        ->and($affinity->events()->count())->toBe(1);
});

it('refuses a claim with no anchor, no subject, or itself on both ends', function (): void {
    expect((new RecordManualAffinity())('tenant-a', '', 'sku-2')->reason)->toBe(RefusalReason::AnchorRequired)
        ->and((new RecordManualAffinity())('tenant-a', 'sku-1', '')->reason)->toBe(RefusalReason::AnchorRequired)
        ->and((new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-1')->reason)->toBe(RefusalReason::AnchorRecommendsItself)
        ->and(Affinity::query()->count())->toBe(0);
});

it('refuses a score the schema could never hold', function (): void {
    expect(fn () => (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 4.0))->toThrow(ScoreOutOfRange::class);
});

it('rescores an existing claim without writing a second audit row for the state it is in', function (): void {
    $first = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.2);
    $again = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.8);

    $affinity = Affinity::query()->findOrFail($first->id);

    expect($again->happened())->toBeFalse()
        ->and($again->id)->toBe($first->id)
        ->and($affinity->ratio())->toBe(0.8)
        ->and($affinity->events()->count())->toBe(1);
});

it('withdraws a claim once, and says so the second time', function (): void {
    $recorded = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2');
    $affinity = Affinity::query()->findOrFail($recorded->id);

    $first = (new WithdrawAffinity())('tenant-a', $affinity);
    $second = (new WithdrawAffinity())('tenant-a', $affinity);

    expect($first->happened())->toBeTrue()
        ->and($second->happened())->toBeFalse()
        ->and($affinity->refresh()->state)->toBe(AffinityState::Superseded)
        ->and($affinity->events()->count())->toBe(2);
});

it('refuses to withdraw another merchant claim', function (): void {
    $recorded = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2');
    $affinity = Affinity::query()->findOrFail($recorded->id);

    expect((new WithdrawAffinity())('tenant-b', $affinity)->reason)->toBe(RefusalReason::NotThisTenants)
        ->and($affinity->refresh()->isActive())->toBeTrue();
});

it('brings a withdrawn claim back when the merchandiser records it again', function (): void {
    $recorded = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2');
    $affinity = Affinity::query()->findOrFail($recorded->id);
    (new WithdrawAffinity())('tenant-a', $affinity);

    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.5);

    expect($affinity->refresh()->state)->toBe(AffinityState::Active)
        ->and($affinity->superseded_at)->toBeNull()
        ->and($affinity->events()->count())->toBe(3);
});

it('lists what a merchant currently claims, and what it used to', function (): void {
    (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2', 0.2);
    $dropped = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-3', 0.9);
    (new WithdrawAffinity())('tenant-a', Affinity::query()->findOrFail($dropped->id));

    expect((new ListAffinities())('tenant-a', 'sku-1'))->toHaveCount(1)
        ->and((new ListAffinities())('tenant-a', 'sku-1', Strategy::Manual))->toHaveCount(1)
        ->and((new ListAffinities())('tenant-a', 'sku-1', Strategy::Popularity))->toHaveCount(0)
        ->and((new ListAffinities())('tenant-a', 'sku-1', null, false))->toHaveCount(2)
        ->and((new ListAffinities())('tenant-b', 'sku-1'))->toHaveCount(0);
});
