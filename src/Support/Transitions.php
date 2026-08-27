<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Models\AffinityEvent;

/**
 * A lifecycle state is a transition that writes its own audit row, never an
 * assignment. The host's generator upserted a score forever and retracted
 * nothing, so nobody could say when a claim stopped being true.
 */
final class Transitions
{
    public static function opened(Affinity $affinity, ?int $runId): void
    {
        self::write($affinity, null, AffinityState::Active, $runId);
    }

    /** False when the state machine forbids the move; nothing is written. */
    public static function to(Affinity $affinity, AffinityState $next, ?int $runId): bool
    {
        $from = $affinity->state;

        if (! $from->canTransitionTo($next)) {
            return false;
        }

        $affinity->forceFill([
            'state' => $next,
            'superseded_at' => $next === AffinityState::Superseded ? Carbon::now() : null,
        ])->save();

        self::write($affinity, $from, $next, $runId);

        return true;
    }

    private static function write(Affinity $affinity, ?AffinityState $from, AffinityState $to, ?int $runId): void
    {
        AffinityEvent::query()->create([
            'tenant_id' => $affinity->tenant_id,
            'affinity_id' => $affinity->id,
            'sequence' => Cast::int(AffinityEvent::query()->where('affinity_id', $affinity->id)->max('sequence')) + 1,
            'from_state' => $from,
            'to_state' => $to,
            'run_id' => $runId,
            'occurred_at' => Carbon::now(),
        ]);
    }
}
