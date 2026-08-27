<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Liberu\Ecommerce\Recommendations\Actions\RecordManualAffinity;
use Liberu\Ecommerce\Recommendations\Actions\ServePlacement;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Models\Affinity;

beforeEach(function (): void {
    Config::set('recommendations.k_anonymity.minimum_subjects', 1);

    foreach (range(1, 6) as $index) {
        (new RecordManualAffinity())('tenant-a', 'sku-anchor', 'sku-'.$index, 0.5);
    }
});

it('gives the same shopper the same answer twice, with no seed and no shuffle', function (): void {
    $first = (new ServePlacement())('tenant-a', 'related', 'sku-anchor', 'person-1', 3);
    $second = (new ServePlacement())('tenant-a', 'related', 'sku-anchor', 'person-1', 3);

    expect($first->productRefs())->toBe($second->productRefs())
        ->and($first->productRefs())->toBe(['sku-1', 'sku-2', 'sku-3']);
});

it('varies only when a seed is asked for, and stores the seed it used', function (): void {
    $seeded = (new ServePlacement())('tenant-a', 'related', 'sku-anchor', 'person-1', 3, 7);
    $again = (new ServePlacement())('tenant-a', 'related', 'sku-anchor', 'person-1', 3, 7);
    $other = (new ServePlacement())('tenant-a', 'related', 'sku-anchor', 'person-1', 3, 8);

    expect($seeded->productRefs())->toBe($again->productRefs())
        ->and($seeded->seed)->toBe(7)
        ->and($seeded->productRefs())->not->toBe($other->productRefs());
});

it('lets the unique key arbitrate two strategies claiming one pair, and refuses the same claim twice', function (): void {
    $insert = fn (Strategy $strategy): bool => (bool) Affinity::query()->insert([
        'tenant_id' => 'tenant-a',
        'strategy' => $strategy->value,
        'from_ref' => 'sku-anchor',
        'to_ref' => 'sku-1',
        'score' => '0.500000',
        'evidence_count' => 1,
        'subject_count' => 1,
        'state' => AffinityState::Active->value,
        'asserted_at' => Carbon::now(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    // A second strategy may claim the pair the merchandiser already claimed;
    // the host's key had no strategy in it, so the second write erased the first.
    expect($insert(Strategy::Popularity))->toBeTrue()
        ->and(fn () => $insert(Strategy::Popularity))->toThrow(QueryException::class)
        ->and(Affinity::query()->where('to_ref', 'sku-1')->count())->toBe(2);
});
