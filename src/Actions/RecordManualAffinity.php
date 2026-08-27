<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Actions;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Data\Outcome;
use Liberu\Ecommerce\Recommendations\Enums\AffinityState;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;
use Liberu\Ecommerce\Recommendations\Models\Affinity;
use Liberu\Ecommerce\Recommendations\Support\Transitions;

/**
 * A merchandiser's own claim. It sits in the same table and ranks in the same
 * list as a computed one, because a curated up-sell and a computed up-sell have
 * to beat each other somewhere and two lists never do.
 */
final class RecordManualAffinity
{
    public function __invoke(string $tenantId, string $fromRef, string $toRef, float $score = 1.0): Outcome
    {
        if ($fromRef === '' || $toRef === '') {
            return Outcome::refused(RefusalReason::AnchorRequired);
        }

        if ($fromRef === $toRef) {
            return Outcome::refused(RefusalReason::AnchorRecommendsItself);
        }

        $existing = Affinity::query()
            ->where('tenant_id', $tenantId)
            ->where('strategy', Strategy::Manual->value)
            ->where('from_ref', $fromRef)
            ->where('to_ref', $toRef)
            ->first();

        if ($existing instanceof Affinity) {
            $existing->fill(['score' => $score, 'asserted_at' => Carbon::now()])->save();
            Transitions::to($existing, AffinityState::Active, null);

            return Outcome::alreadyRecorded($existing->id);
        }

        $affinity = Affinity::query()->create([
            'tenant_id' => $tenantId,
            'strategy' => Strategy::Manual,
            'from_ref' => $fromRef,
            'to_ref' => $toRef,
            'score' => $score,
            'evidence_count' => 1,
            'subject_count' => 0,
            'state' => AffinityState::Active,
            'run_id' => null,
            'asserted_at' => Carbon::now(),
        ]);

        Transitions::opened($affinity, null);

        return Outcome::recorded($affinity->id);
    }
}
