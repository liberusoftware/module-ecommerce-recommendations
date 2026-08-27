<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Actions\RecordManualAffinity;
use Liberu\Ecommerce\Recommendations\Actions\WithdrawAffinity;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Exceptions\AffinityHistoryIsAppendOnly;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\AffinityEvent;

function historyFor(): Affinity
{
    $recorded = (new RecordManualAffinity())('tenant-a', 'sku-1', 'sku-2');
    $affinity = Affinity::query()->findOrFail($recorded->id);
    (new WithdrawAffinity())('tenant-a', $affinity);

    return $affinity;
}

it('numbers an audit row in sequence and records what the state was before', function (): void {
    $affinity = historyFor();
    $events = $affinity->events()->orderBy('sequence')->get();

    expect($events->pluck('sequence')->all())->toBe([1, 2])
        ->and($events[0]->from_state)->toBeNull()
        ->and($events[0]->to_state)->toBe(AffinityState::Active)
        ->and($events[1]->from_state)->toBe(AffinityState::Active)
        ->and($events[1]->to_state)->toBe(AffinityState::Superseded);
});

it('never rewrites or removes an audit row', function (): void {
    $event = historyFor()->events()->firstOrFail();

    expect(fn () => $event->forceFill(['to_state' => AffinityState::Superseded])->save())
        ->toThrow(AffinityHistoryIsAppendOnly::class)
        ->and(fn () => $event->delete())->toThrow(AffinityHistoryIsAppendOnly::class)
        ->and(AffinityEvent::query()->count())->toBe(2);
});

it('lets the unique index arbitrate two appends racing for one sequence', function (): void {
    $affinity = historyFor();

    $append = fn (): bool => (bool) AffinityEvent::query()->insert([
        'tenant_id' => 'tenant-a',
        'affinity_id' => $affinity->id,
        'sequence' => 3,
        'from_state' => AffinityState::Superseded->value,
        'to_state' => AffinityState::Active->value,
        'occurred_at' => Carbon::now(),
    ]);

    expect($append())->toBeTrue()
        ->and($append)->toThrow(QueryException::class)
        ->and(AffinityEvent::query()->where('affinity_id', $affinity->id)->count())->toBe(3);
});
